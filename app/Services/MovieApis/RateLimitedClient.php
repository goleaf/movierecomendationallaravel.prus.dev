<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use App\Support\Http\Batching;
use App\Support\Http\Exceptions\RateLimitExceededTransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class RateLimitedClient
{
    protected string $baseUrl;

    protected float $timeout;

    protected int $retryAttempts;

    protected int $retryDelayMs;

    protected float $backoffMultiplier;

    protected int $backoffMaxDelayMs;

    protected int $retryJitterMs;

    protected int $concurrency;

    protected int $rateLimitWindow;

    protected int $rateLimitAllowance;

    protected string $rateLimiterKey;

    protected string $serviceName;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultQuery = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaultHeaders = [];

    public function __construct(
        protected HttpFactory $http,
        RateLimitedClientConfig $config,
    ) {
        $this->baseUrl = $config->baseUrl();
        $this->timeout = $config->timeout();
        $this->retryAttempts = $config->retryAttempts();
        $this->retryDelayMs = $config->retryDelayMs();
        $this->backoffMultiplier = $config->backoffMultiplier();
        $this->backoffMaxDelayMs = $config->backoffMaxDelayMs();
        $this->retryJitterMs = $config->retryJitterMs();
        $this->concurrency = $config->concurrency();
        $this->rateLimitWindow = $config->rateLimitWindow();
        $this->rateLimitAllowance = $config->rateLimitAllowance();
        $this->defaultQuery = $config->defaultQuery();
        $this->defaultHeaders = $config->defaultHeaders();
        $this->rateLimiterKey = $config->rateLimiterKey();
        $this->serviceName = $config->serviceName();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], array $options = []): array
    {
        return $this->request('GET', $path, $query, $options);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $query = [], array $options = []): array
    {
        $results = $this->batch([
            [
                'key' => 'single',
                'method' => $method,
                'path' => $path,
                'query' => $query,
                'options' => $options,
                'transform' => static fn (Response $response): array => $response->json() ?? [],
            ],
        ]);

        $result = $results['single'] ?? null;

        if (! is_array($result)) {
            throw new RuntimeException('Request did not return a valid response.');
        }

        return $result;
    }

    /**
     * @param  list<array{key?:string,method?:string,path?:string,query?:array<string,mixed>,options?:array<string,mixed>,transform?:callable(Response):mixed}>  $requests
     * @return array<string, mixed>
     */
    public function batch(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $results = [];
        $chunks = array_chunk($requests, max(1, $this->concurrency));

        foreach ($chunks as $chunk) {
            $batch = $this->createBatch();
            $traceId = (string) Str::uuid();
            $chunkMap = [];
            $attempts = [];
            $startedAt = [];
            $metrics = [
                'success' => 0,
                'failed' => 0,
                'retries' => 0,
                'total' => count($chunk),
            ];

            $batch->before(function () use ($traceId, $metrics): void {
                Log::channel('ingestion')->info('ingestion.http.batch_start', [
                    'trace_id' => $traceId,
                    'service' => $this->serviceName,
                    'total_requests' => $metrics['total'],
                ]);
            });

            $batch->progress(function (Batching $batch, int|string $key, Response $response) use (&$attempts, &$metrics, &$startedAt, $traceId): void {
                $attemptCount = $attempts[$key] ?? 1;
                $durationMs = $this->elapsedMs($startedAt[$key] ?? null);

                $metrics['success']++;
                $metrics['retries'] += max(0, $attemptCount - 1);

                Log::channel('ingestion')->info('ingestion.http.batch_success', [
                    'trace_id' => $traceId,
                    'service' => $this->serviceName,
                    'request_key' => $key,
                    'status' => $response->status(),
                    'attempts' => $attemptCount,
                    'duration_ms' => $durationMs,
                ]);
            });

            $batch->catch(function (Batching $batch, int|string $key, Response|RequestException|ConnectionException $reason) use (&$attempts, &$metrics, &$startedAt, $traceId): void {
                $attemptCount = $attempts[$key] ?? 1;
                $durationMs = $this->elapsedMs($startedAt[$key] ?? null);
                $metrics['failed']++;
                $metrics['retries'] += max(0, $attemptCount - 1);

                $context = [
                    'trace_id' => $traceId,
                    'service' => $this->serviceName,
                    'request_key' => $key,
                    'attempts' => $attemptCount,
                    'duration_ms' => $durationMs,
                ];

                if ($reason instanceof Response) {
                    $context['status'] = $reason->status();
                } else {
                    $context['exception'] = $reason::class;
                    $context['message'] = $reason->getMessage();
                }

                Log::channel('ingestion')->warning('ingestion.http.batch_failure', $context);
            });

            $batch->finally(function () use (&$metrics, $traceId): void {
                Log::channel('ingestion')->info('ingestion.http.batch_complete', [
                    'trace_id' => $traceId,
                    'service' => $this->serviceName,
                    'success' => $metrics['success'],
                    'failed' => $metrics['failed'],
                    'retries' => $metrics['retries'],
                ]);
            });

            foreach ($chunk as $index => $definition) {
                $key = (string) ($definition['key'] ?? $index);
                $chunkMap[$key] = $definition;
                $method = strtoupper((string) ($definition['method'] ?? 'GET'));
                $path = (string) ($definition['path'] ?? '/');
                $query = (array) ($definition['query'] ?? []);
                $options = (array) ($definition['options'] ?? []);

                $pending = $batch->as($key);

                $pending->beforeSending(function (HttpRequest $request) use ($key, &$attempts, &$startedAt): void {
                    $attempts[$key] = ($attempts[$key] ?? 0) + 1;

                    if (! isset($startedAt[$key])) {
                        $startedAt[$key] = hrtime(true);
                    }

                    if (RateLimiter::tooManyAttempts($this->rateLimiterKey, $this->rateLimitAllowance)) {
                        throw new RateLimitExceededTransferException(new TooManyRequestsHttpException(
                            $this->rateLimitWindow,
                            sprintf('Rate limit exceeded for %s', $this->rateLimiterKey),
                        ));
                    }

                    RateLimiter::hit($this->rateLimiterKey, $this->rateLimitWindow);
                });

                if (($options['headers'] ?? []) !== []) {
                    $pending->withHeaders((array) $options['headers']);
                }

                if (($options['query'] ?? []) !== []) {
                    $pending->withQueryParameters((array) $options['query']);
                }

                if (($options['options'] ?? []) !== []) {
                    $pending->withOptions((array) $options['options']);
                }

                $pending->throw();

                $pending->send($method, ltrim($path, '/'), [
                    'query' => $this->buildQuery($query, $options),
                ]);
            }

            $responses = $batch->send();

            foreach ($responses as $key => $payload) {
                $definition = $chunkMap[(string) $key] ?? null;

                if ($definition === null) {
                    continue;
                }

                if ($payload instanceof Response) {
                    $transform = $definition['transform'] ?? static fn (Response $response): mixed => $response->json() ?? [];
                    $results[(string) $key] = $transform($payload);

                    continue;
                }

                if ($payload instanceof RateLimitExceededTransferException) {
                    throw $payload->toHttpException();
                }

                if ($payload instanceof RequestException || $payload instanceof ConnectionException) {
                    if ($payload instanceof RequestException) {
                        $response = $payload->response;

                        if ($response instanceof Response && $response->status() === 429) {
                            throw new TooManyRequestsHttpException($this->rateLimitWindow, sprintf(
                                'Rate limit exceeded for %s',
                                $this->rateLimiterKey,
                            ), $payload);
                        }
                    }

                    throw $payload;
                }

                if ($payload instanceof Throwable) {
                    throw $payload;
                }

                $results[(string) $key] = $payload;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildQuery(array $query, array $options = []): array
    {
        $optionQuery = (array) Arr::get($options, 'query', []);
        $merged = array_merge($optionQuery, $query);

        return array_filter($merged, static fn ($value) => $value !== null);
    }

    protected function shouldRetryResponse(Response $response): bool
    {
        return $response->serverError() || $response->tooManyRequests();
    }

    /**
     * @return list<int>
     */
    protected function retryDelays(): array
    {
        if ($this->retryAttempts <= 0) {
            return [];
        }

        $delays = [];
        $delay = $this->retryDelayMs;

        for ($attempt = 0; $attempt < $this->retryAttempts; $attempt++) {
            $delayWithJitter = $delay;

            if ($this->retryJitterMs > 0) {
                $delayWithJitter += random_int(0, $this->retryJitterMs);
            }

            if ($this->backoffMaxDelayMs > 0) {
                $delayWithJitter = min($delayWithJitter, $this->backoffMaxDelayMs);
            }

            $delays[] = max(0, $delayWithJitter);

            $delay = $this->nextDelay($delay);
        }

        return $delays;
    }

    protected function nextDelay(int $currentDelay): int
    {
        if ($currentDelay <= 0) {
            return $currentDelay;
        }

        $multiplier = $this->backoffMultiplier;

        if ($multiplier <= 1.0) {
            return $currentDelay;
        }

        $nextDelay = (int) ceil($currentDelay * $multiplier);

        if ($this->backoffMaxDelayMs > 0) {
            $nextDelay = min($nextDelay, $this->backoffMaxDelayMs);
        }

        return max($currentDelay, $nextDelay);
    }

    protected function createBatch(): Batching
    {
        return new Batching($this->http, [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'default_query' => $this->defaultQuery,
            'default_headers' => $this->defaultHeaders,
            'retry' => [
                'delays' => $this->retryDelays(),
                'when' => fn (Throwable $exception): bool => $this->shouldRetryException($exception),
            ],
            'concurrency' => $this->concurrency,
        ]);
    }

    protected function shouldRetryException(Throwable $exception): bool
    {
        if ($exception instanceof RequestException) {
            $response = $exception->response;

            if ($response instanceof Response) {
                return $this->shouldRetryResponse($response);
            }
        }

        return $exception instanceof ConnectionException;
    }

    protected function elapsedMs(?int $startedAt): float
    {
        if ($startedAt === null) {
            return 0.0;
        }

        $elapsed = hrtime(true) - $startedAt;

        return round($elapsed / 1_000_000, 2);
    }
}

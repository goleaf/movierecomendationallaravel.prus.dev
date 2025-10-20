<?php

declare(strict_types=1);

namespace App\Services\MovieApis;

use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BatchedRateLimitedClient extends RateLimitedClient
{
    public function __construct(
        HttpFactory $http,
        RateLimitedClientConfig $config,
        protected string $service = 'movie-api',
    ) {
        parent::__construct($http, $config);
    }

    /**
     * @param  iterable<int|string, array<string, mixed>>  $requests
     * @param  array<string, mixed>  $options
     * @return array<int|string, array<string, mixed>>
     */
    public function batch(iterable $requests, array $options = []): array
    {
        $payload = is_array($requests) ? $requests : iterator_to_array($requests, true);

        if ($payload === []) {
            return [];
        }

        $concurrency = max(1, (int) ($options['concurrency'] ?? $this->batchConcurrency));
        $headers = array_merge($this->batchHeaders, (array) ($options['headers'] ?? []));
        $retry = (array) ($options['retry'] ?? []);
        $retryAttempts = max(0, (int) ($retry['attempts'] ?? $this->batchRetryAttempts));
        $retryDelay = max(0, (int) ($retry['delay_ms'] ?? $this->batchRetryDelayMs));

        $results = [];
        $orderedKeys = array_keys($payload);

        foreach ($this->chunkRequests($payload, $concurrency) as $chunk) {
            $metadata = [];
            $exceptions = [];

            $batch = $this->http->baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->batch(function (Batch $batch) use ($chunk, $headers, $retryAttempts, $retryDelay, &$metadata): void {
                    foreach ($chunk as $key => $definition) {
                        $this->ensureRateLimitAllowance();

                        $normalised = $this->normaliseDefinition($definition);
                        $metadata[$key] = $normalised;

                        $request = $batch->as((string) $key)
                            ->baseUrl($this->baseUrl)
                            ->timeout($this->timeout)
                            ->acceptJson();

                        if ($retryAttempts > 0) {
                            $request = $request->retry($retryAttempts, $retryDelay);
                        }

                        if ($this->defaultHeaders !== []) {
                            $request = $request->withHeaders($this->defaultHeaders);
                        }

                        if ($headers !== []) {
                            $request = $request->withHeaders($headers);
                        }

                        if ($normalised['headers'] !== []) {
                            $request = $request->withHeaders($normalised['headers']);
                        }

                        if ($this->defaultQuery !== []) {
                            $request = $request->withQueryParameters($this->defaultQuery);
                        }

                        $request->send(
                            $normalised['method'],
                            $normalised['path'],
                            [
                                'query' => $normalised['query'],
                            ],
                        );
                    }
                });

            $batch->progress(function (Batch $progress, $key, Response $response) use (&$results, &$metadata): void {
                $this->logTrace('success', $key, $metadata[$key] ?? [], $response);

                $results[$key] = $response->json() ?? [];
            });

            $batch->catch(function (Batch $progress, $key, Response|RequestException|ConnectionException|Throwable $reason) use (&$exceptions, &$metadata): void {
                $this->logTrace('failure', $key, $metadata[$key] ?? [], $reason);

                if ($reason instanceof Response) {
                    $exceptions[] = $reason->toException() ?? new RequestException($reason);

                    return;
                }

                if ($reason instanceof RequestException || $reason instanceof ConnectionException || $reason instanceof Throwable) {
                    $exceptions[] = $reason instanceof Throwable ? $reason : new RuntimeException('Batch request failed.');
                }
            });

            $batch->send();

            if ($exceptions !== []) {
                throw $exceptions[0];
            }
        }

        return $this->orderedResults($orderedKeys, $results);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $requests
     * @return array<int|string, array<string, mixed>>
     */
    protected function orderedResults(array $orderedKeys, array $results): array
    {
        $ordered = [];

        foreach ($orderedKeys as $key) {
            if (! array_key_exists($key, $results)) {
                throw new RuntimeException(sprintf('Missing response for request [%s].', (string) $key));
            }

            $ordered[$key] = $results[$key];
        }

        return $ordered;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $requests
     * @return array<int, array<int|string, array<string, mixed>>>
     */
    protected function chunkRequests(array $requests, int $chunkSize): array
    {
        $chunks = [];
        $current = [];

        foreach ($requests as $key => $definition) {
            $current[$key] = $definition;

            if (count($current) >= $chunkSize) {
                $chunks[] = $current;
                $current = [];
            }
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{method: string, path: string, query: array<string, mixed>, headers: array<string, mixed>, trace: array<string, mixed>}
     */
    protected function normaliseDefinition(array $definition): array
    {
        $method = strtoupper((string) ($definition['method'] ?? 'GET'));
        $path = (string) ($definition['path'] ?? '/');
        $path = $path === '' ? '/' : ($path === '/' ? '/' : ltrim($path, '/'));
        $query = $this->filterQuery((array) ($definition['query'] ?? []));
        $headers = $this->normaliseHeaders((array) ($definition['headers'] ?? []));
        $trace = array_filter((array) ($definition['trace'] ?? []), static fn ($value) => $value !== null && $value !== '');

        return [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'headers' => $headers,
            'trace' => $trace,
        ];
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function normaliseHeaders(array $headers): array
    {
        $normalised = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key) || $key === '' || $value === null) {
                continue;
            }

            $normalised[$key] = $value;
        }

        return $normalised;
    }

    protected function logTrace(string $status, string|int $key, array $meta, Response|RequestException|ConnectionException|Throwable $result): void
    {
        $context = [
            'service' => $this->service,
            'request_key' => (string) $key,
            'status' => $status,
            'method' => $meta['method'] ?? 'GET',
            'path' => $meta['path'] ?? '/',
            'query' => $meta['query'] ?? [],
            'trace' => $meta['trace'] ?? [],
            'rate_limiter_key' => $this->rateLimiterKey,
        ];

        if ($result instanceof Response) {
            $context['status_code'] = $result->status();
            $stats = $result->handlerStats();
            $context['duration_ms'] = isset($stats['total_time'])
                ? (int) round($stats['total_time'] * 1000)
                : null;
        } else {
            $context['exception'] = $result::class;
            $context['message'] = $result->getMessage();
        }

        if (! isset($context['trace_id'])) {
            $context['trace_id'] = $meta['trace']['id'] ?? (string) Str::uuid();
        }

        Log::channel('ingestion')->info('movie_api.batch.trace', $context);
    }
}

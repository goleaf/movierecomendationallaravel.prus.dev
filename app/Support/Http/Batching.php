<?php

declare(strict_types=1);

namespace App\Support\Http;

use GuzzleHttp\Promise\EachPromise;
use Illuminate\Http\Client\Batch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class Batching extends Batch
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected ?int $configuredConcurrency;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(HttpFactory $factory, array $config = [])
    {
        parent::__construct($factory);

        $this->config = $config;
        $concurrency = (int) ($config['concurrency'] ?? 0);
        $this->configuredConcurrency = $concurrency > 0 ? $concurrency : null;
    }

    protected function asyncRequest(): PendingRequest
    {
        $request = parent::asyncRequest();

        if (isset($this->config['base_url'])) {
            $request = $request->baseUrl((string) $this->config['base_url']);
        }

        if (isset($this->config['timeout'])) {
            $request = $request->timeout((float) $this->config['timeout']);
        }

        $request = $request->acceptJson();

        if (! empty($this->config['default_query'])) {
            $request = $request->withQueryParameters((array) $this->config['default_query']);
        }

        if (! empty($this->config['default_headers'])) {
            $request = $request->withHeaders((array) $this->config['default_headers']);
        }

        if (! empty($this->config['options'])) {
            $request = $request->withOptions((array) $this->config['options']);
        }

        if (! empty($this->config['retry'])) {
            $retry = (array) $this->config['retry'];
            $delays = $retry['delays'] ?? [];
            $when = $retry['when'] ?? null;
            $throw = $retry['throw'] ?? true;
            $times = $retry['times'] ?? 0;
            $sleep = $retry['sleep'] ?? 0;

            if (is_array($delays) && $delays !== []) {
                $request = $request->retry($delays, 0, $when, $throw);
            } elseif (is_int($times) && $times > 0) {
                $request = $request->retry($times, $sleep, $when, $throw);
            }
        }

        return $request;
    }

    public function send(): array
    {
        $this->inProgress = true;

        if ($this->beforeCallback !== null) {
            call_user_func($this->beforeCallback, $this);
        }

        $results = [];
        $promises = [];

        foreach ($this->requests as $key => $item) {
            $promise = match (true) {
                $item instanceof PendingRequest => $item->getPromise(),
                default => $item,
            };

            $promises[$key] = $promise;
        }

        if ($promises !== []) {
            $options = [
                'fulfilled' => function ($result, $key) use (&$results) {
                    $results[$key] = $result;

                    $this->decrementPendingRequests();

                    if ($result instanceof Response && $result->successful()) {
                        if ($this->progressCallback !== null) {
                            call_user_func($this->progressCallback, $this, $key, $result);
                        }

                        return $result;
                    }

                    if (
                        ($result instanceof Response && $result->failed()) ||
                        $result instanceof RequestException ||
                        $result instanceof ConnectionException
                    ) {
                        $this->incrementFailedRequests();

                        if ($this->catchCallback !== null) {
                            call_user_func($this->catchCallback, $this, $key, $result);
                        }
                    }

                    return $result;
                },
                'rejected' => function ($reason, $key) use (&$results) {
                    $results[$key] = $reason;

                    $this->decrementPendingRequests();

                    if ($reason instanceof RequestException || $reason instanceof ConnectionException) {
                        $this->incrementFailedRequests();

                        if ($this->catchCallback !== null) {
                            call_user_func($this->catchCallback, $this, $key, $reason);
                        }
                    }

                    return $reason;
                },
            ];

            if ($this->configuredConcurrency !== null) {
                $options['concurrency'] = $this->configuredConcurrency;
            }

            (new EachPromise($promises, $options))->promise()->wait();
        }

        if (! $this->hasFailures() && $this->thenCallback !== null) {
            call_user_func($this->thenCallback, $this, $results);
        }

        if ($this->finallyCallback !== null) {
            call_user_func($this->finallyCallback, $this, $results);
        }

        $this->finishedAt = new \Carbon\CarbonImmutable;
        $this->inProgress = false;

        return $results;
    }
}

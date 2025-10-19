<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SsrMetricsMiddleware;
use App\Jobs\StoreSsrMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SsrMetricsMiddlewareTest extends TestCase
{
    public function test_metrics_middleware_dispatches_job_after_response(): void
    {
        Queue::fake();
        config([
            'ssrmetrics.enabled' => true,
            'ssrmetrics.paths' => ['/tracked'],
        ]);

        $middleware = new SsrMetricsMiddleware;

        $request = Request::create('/tracked', 'GET');
        $response = new Response('<html><head><meta name="description" content="Test"><meta property="og:title" content="Title"><script type="application/ld+json">{}</script></head><body><img src="/image.jpg"><script src="/app.js"></script></body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        $middleware->handle($request, static fn () => $response);

        app()->terminate();

        Queue::assertPushed(StoreSsrMetric::class, function (StoreSsrMetric $job): bool {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('metric');
            $property->setAccessible(true);

            /** @var array<string, mixed> $metric */
            $metric = $property->getValue($job);

            return $metric['path'] === '/tracked'
                && $metric['score'] < 100
                && Str::of((string) $metric['captured_at'])->isNotEmpty();
        });
    }
}

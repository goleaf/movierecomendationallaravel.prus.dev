<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SsrMetricsMiddleware;
use App\Jobs\StoreSsrMetric;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SsrMetricsMiddlewareTest extends TestCase
{
    public function test_it_dispatches_metrics_when_config_enabled_and_path_matches(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', true);
        config()->set('ssrmetrics.paths', ['/test']);

        $html = <<<'HTML'
            <html>
                <head>
                    <meta charset="utf-8">
                    <meta property="og:title" content="Example">
                    <script type="application/ld+json">{"@context":"https://schema.org"}</script>
                </head>
                <body>
                    <img src="image.jpg" alt="Example">
                    <script src="/app.js" defer></script>
                </body>
            </html>
        HTML;

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $request = Request::create('/test', 'GET');

        $middleware = new SsrMetricsMiddleware;

        $result = $middleware->handle($request, static fn () => $response);

        $this->assertSame($response, $result);

        $expectedSize = strlen($html);

        Queue::assertPushed(StoreSsrMetric::class, function (StoreSsrMetric $job) use ($expectedSize): bool {
            $payload = $job->payload;

            if ($payload['path'] !== '/test') {
                return false;
            }

            if ($payload['score'] !== 90) {
                return false;
            }

            $expectedCounts = [
                'html_bytes' => $expectedSize,
                'html_size' => $expectedSize,
                'meta_count' => 2,
                'og_count' => 1,
                'ldjson_count' => 1,
                'img_count' => 1,
                'blocking_scripts' => 0,
            ];

            foreach ($expectedCounts as $key => $value) {
                if (! array_key_exists($key, $payload) || $payload[$key] !== $value) {
                    return false;
                }
            }

            if (! isset($payload['first_byte_ms']) || ! is_int($payload['first_byte_ms']) || $payload['first_byte_ms'] < 0) {
                return false;
            }

            if (! isset($payload['collected_at']) || ! is_string($payload['collected_at'])) {
                return false;
            }

            try {
                Carbon::parse($payload['collected_at']);
            } catch (\Throwable) {
                return false;
            }

            if (($payload['has_json_ld'] ?? null) !== true) {
                return false;
            }

            if (($payload['has_open_graph'] ?? null) !== true) {
                return false;
            }

            if (! isset($payload['meta']) || ! is_array($payload['meta'])) {
                return false;
            }

            $meta = $payload['meta'];

            if (($meta['first_byte_ms'] ?? null) !== $payload['first_byte_ms']) {
                return false;
            }

            foreach (['html_bytes', 'html_size', 'meta_count', 'og_count', 'ldjson_count', 'img_count', 'blocking_scripts'] as $key) {
                if (($meta[$key] ?? null) !== $payload[$key]) {
                    return false;
                }
            }

            if (($meta['has_json_ld'] ?? null) !== true) {
                return false;
            }

            if (($meta['has_open_graph'] ?? null) !== true) {
                return false;
            }

            return true;
        });
    }

    public function test_it_uses_configured_penalties_when_calculating_score(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', true);
        config()->set('ssrmetrics.paths', ['/test']);
        config()->set('ssrmetrics.penalties.blocking_scripts.per_script', 7);
        config()->set('ssrmetrics.penalties.blocking_scripts.max', 21);
        config()->set('ssrmetrics.penalties.missing_ldjson.deduction', 3);
        config()->set('ssrmetrics.penalties.low_og.minimum', 2);
        config()->set('ssrmetrics.penalties.low_og.deduction', 5);
        config()->set('ssrmetrics.penalties.oversized_html.threshold', 512);
        config()->set('ssrmetrics.penalties.oversized_html.deduction', 8);
        config()->set('ssrmetrics.penalties.excess_images.threshold', 1);
        config()->set('ssrmetrics.penalties.excess_images.deduction', 4);

        $bodyContent = str_repeat('<p>content</p>', 60);

        $html = <<<HTML
            <html>
                <head>
                    <meta property="og:title" content="Example">
                </head>
                <body>
                    {$bodyContent}
                    <img src="image-a.jpg" alt="Example A">
                    <img src="image-b.jpg" alt="Example B">
                    <script src="/app.js"></script>
                </body>
            </html>
        HTML;

        $response = new Response($html, 200, ['Content-Type' => 'text/html']);
        $request = Request::create('/test', 'GET');

        $middleware = new SsrMetricsMiddleware;

        $middleware->handle($request, static fn () => $response);

        Queue::assertPushed(StoreSsrMetric::class, function (StoreSsrMetric $job): bool {
            $payload = $job->payload;

            return $payload['score'] === 73;
        });
    }

    public function test_it_skips_when_feature_disabled(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', false);
        config()->set('ssrmetrics.paths', ['/test']);

        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        $request = Request::create('/test', 'GET');

        $middleware = new SsrMetricsMiddleware;

        $middleware->handle($request, static fn () => $response);

        Queue::assertNothingPushed();
    }

    public function test_it_skips_when_path_not_monitored(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', true);
        config()->set('ssrmetrics.paths', ['/other']);

        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html']);
        $request = Request::create('/test', 'GET');

        $middleware = new SsrMetricsMiddleware;

        $middleware->handle($request, static fn () => $response);

        Queue::assertNothingPushed();
    }
}

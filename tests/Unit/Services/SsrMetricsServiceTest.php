<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SsrMetricsService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SsrMetricsServiceTest extends TestCase
{
    public function test_monitored_paths_are_normalised(): void
    {
        config()->set('ssrmetrics', [
            'enabled' => true,
            'paths' => ['/', 'analytics', '/analytics', ' analytics ', '', '  /deep/nested  '],
        ]);

        $service = new SsrMetricsService;

        $this->assertSame(['/', '/analytics', '/deep/nested'], $service->monitoredPaths());
    }

    public function test_missing_config_is_handled_gracefully(): void
    {
        config()->set('ssrmetrics', null);

        $service = new SsrMetricsService;

        $this->assertFalse($service->isEnabled());
        $this->assertSame([], $service->monitoredPaths());
        $this->assertNull($service->compute('/any', '<html></html>', 'text/html', 10));
    }

    public function test_score_calculation_applies_penalties(): void
    {
        config()->set('ssrmetrics', [
            'enabled' => true,
            'paths' => ['/movies'],
            'penalties' => [
                'blocking_scripts' => ['per_script' => 7, 'max' => 15],
                'missing_ldjson' => ['deduction' => 11],
                'low_og' => ['minimum' => 2, 'deduction' => 9],
                'oversized_html' => ['threshold' => 120, 'deduction' => 8],
                'excess_images' => ['threshold' => 2, 'deduction' => 4],
            ],
        ]);

        Carbon::setTestNow('2024-01-01 00:00:00');

        $html = <<<'HTML'
<html><head>
<meta name="description" content="...">
<meta property="og:title" content="...">
</head><body>
<script src="blocking.js"></script>
<script src="another.js"></script>
<img src="a.jpg" />
<img src="b.jpg" />
<img src="c.jpg" />
HTML;
        $html .= str_repeat('x', 150);
        $html .= '</body></html>';

        $service = new SsrMetricsService;

        $result = $service->compute('/movies', $html, 'text/html; charset=UTF-8', 123);

        $this->assertNotNull($result);
        $this->assertSame(54, $result['score']);

        $payload = $result['payload'];

        $this->assertSame('/movies', $payload['path']);
        $this->assertSame(54, $payload['score']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $payload['collected_at']);
        $this->assertSame(123, $payload['first_byte_ms']);
        $this->assertSame(2, $payload['blocking_scripts']);
        $this->assertSame(3, $payload['img_count']);
        $this->assertSame(0, $payload['ldjson_count']);
        $this->assertSame(1, $payload['og_count']);
        $this->assertSame(2, $payload['meta_count']);

        $this->assertSame(2, $payload['meta']['blocking_scripts']);
        $this->assertFalse($payload['meta']['has_json_ld']);
        $this->assertTrue($payload['meta']['has_open_graph']);

        Carbon::setTestNow();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AdminRoutesDeprecationTest extends TestCase
{
    public function testCtrRouteRedirectsToAnalyticsPanel(): void
    {
        $response = $this->get('/admin/ctr');

        $response->assertRedirect('/analytics/ctr');
    }

    public function testMetricsRouteRedirectsToQueuePage(): void
    {
        $response = $this->get('/admin/metrics');

        $response->assertRedirect('/analytics/queue');
    }
}

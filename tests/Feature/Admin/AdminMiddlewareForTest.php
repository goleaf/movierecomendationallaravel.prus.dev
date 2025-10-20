<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class AdminMiddlewareForTest extends TestCase
{
    public function test_index_route_excludes_audit_and_track_middleware(): void
    {
        $route = $this->resolveRoute('admin.inquiries.index');

        $middleware = $route->gatherMiddleware();

        $this->assertNotContains('admin.audit', $middleware);
        $this->assertNotContains('admin.track', $middleware);
    }

    public function test_mutating_routes_include_audit_and_track_middleware(): void
    {
        foreach (['admin.inquiries.store', 'admin.inquiries.update', 'admin.inquiries.destroy'] as $routeName) {
            $route = $this->resolveRoute($routeName);
            $middleware = $route->gatherMiddleware();

            $this->assertContains('admin.audit', $middleware);
            $this->assertContains('admin.track', $middleware);
        }
    }

    private function resolveRoute(string $name): Route
    {
        $route = RouteFacade::getRoutes()->getByName($name);

        $this->assertNotNull($route, sprintf('Failed asserting that route [%s] exists.', $name));

        return $route;
    }
}

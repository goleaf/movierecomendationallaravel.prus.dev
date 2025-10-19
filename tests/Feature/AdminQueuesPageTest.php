<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminQueuesPageTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdEnv = false;

    private ?string $envPath = null;

    protected function setUp(): void
    {
        $this->envPath = dirname(__DIR__, 2).'/.env';

        if ($this->envPath !== null && ! file_exists($this->envPath)) {
            $key = base64_encode(random_bytes(32));
            file_put_contents($this->envPath, "APP_KEY=base64:$key\nAPP_ENV=testing\n");
            $this->createdEnv = true;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();

        if ($this->createdEnv && $this->envPath !== null && file_exists($this->envPath)) {
            unlink($this->envPath);
            $this->createdEnv = false;
        }
    }

    public function test_queue_page_requires_authentication(): void
    {
        $route = Route::getRoutes()->getByName('admin.queues');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_admin_sees_queue_metrics_timeline(): void
    {
        $now = CarbonImmutable::parse('2025-02-14 12:00:00');
        CarbonImmutable::setTestNow($now);

        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now->subMinutes(2)->getTimestamp(),
                'created_at' => $now->subMinutes(2)->getTimestamp(),
            ],
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now->subMinute()->getTimestamp(),
                'created_at' => $now->subMinute()->getTimestamp(),
            ],
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now->subMinute()->getTimestamp(),
                'created_at' => $now->subMinute()->getTimestamp(),
            ],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Test failure',
                'failed_at' => $now->subMinutes(2)->toDateTimeString(),
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/queues');

        $response->assertOk();
        $response->assertViewIs('admin.queues');
        $response->assertSeeText(__('admin.queues.jobs_chart'));
        $response->assertSeeText(__('admin.queues.failures_chart'));

        $response->assertViewHas('timeline', function (array $timeline) use ($now): bool {
            $points = $timeline['points'];
            $lastPoint = end($points);

            $this->assertSame($now->setSecond(0)->setMicrosecond(0)->toIso8601String(), $lastPoint['timestamp']);
            $this->assertSame(0, $lastPoint['failures']);
            $this->assertSame(0, $lastPoint['jobs']);

            $previousIndex = array_key_last($points) - 1;
            $previousPoint = $previousIndex >= 0 ? $points[$previousIndex] : null;

            $this->assertNotNull($previousPoint);
            $this->assertSame(2, $previousPoint['jobs']);
            $this->assertSame(0, $previousPoint['failures']);

            $twoMinutesIndex = array_key_last($points) - 2;
            $twoMinutesAgo = $twoMinutesIndex >= 0 ? $points[$twoMinutesIndex] : null;

            $this->assertNotNull($twoMinutesAgo);
            $this->assertSame(1, $twoMinutesAgo['jobs']);
            $this->assertSame(1, $twoMinutesAgo['failures']);

            return true;
        });
    }

    public function test_admin_can_export_queue_metrics_as_csv(): void
    {
        $now = CarbonImmutable::parse('2025-02-14 12:00:00');
        CarbonImmutable::setTestNow($now);

        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now->getTimestamp(),
                'created_at' => $now->getTimestamp(),
            ],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/queues/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('timestamp,jobs,failures', $csv);
        $this->assertStringContainsString($now->setSecond(0)->setMicrosecond(0)->toIso8601String().',1,0', $csv);
    }
}

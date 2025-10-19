<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminQueuesPageTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdEnvFile = false;

    private string $envPath;

    protected function setUp(): void
    {
        putenv('QUEUE_MANAGEMENT_ADMINS=ops@example.com');
        $_ENV['QUEUE_MANAGEMENT_ADMINS'] = 'ops@example.com';
        $_SERVER['QUEUE_MANAGEMENT_ADMINS'] = 'ops@example.com';

        $this->envPath = dirname(__DIR__, 2).'/.env';

        if (! file_exists($this->envPath)) {
            file_put_contents($this->envPath, '');
            $this->createdEnvFile = true;
        }

        parent::setUp();

        Authenticate::redirectUsing(static fn () => '/login');
    }

    protected function tearDown(): void
    {
        if ($this->createdEnvFile && file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.queues'));

        $response->assertStatus(302);
        $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.queues'));

        $response->assertForbidden();
    }

    public function test_admin_sees_queue_metrics(): void
    {
        $this->seedQueueData();

        $admin = User::factory()->create([
            'email' => 'ops@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.queues'));

        $response->assertOk();
        $response->assertSeeText('importers');
        $response->assertSeeText('recommendations');
        $response->assertSeeText('300.00s');
        $response->assertSeeText('1.80');
        $response->assertSeeText('120.00s');
        $response->assertSeeText('2.50');
        $response->assertSee(__('admin.queues.download_csv'), false);
    }

    public function test_csv_export_is_available(): void
    {
        $this->seedQueueData();

        $admin = User::factory()->create([
            'email' => 'ops@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.queues', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('queue,in_flight,failed,avg_runtime_seconds,jobs_per_minute,processed_jobs,batches', $csv);
        $this->assertStringContainsString('importers,2,1,300.00,1.80,9,1', $csv);
        $this->assertStringContainsString('recommendations,1,0,120.00,2.50,5,1', $csv);
    }

    private function seedQueueData(): void
    {
        $now = CarbonImmutable::now();
        $importerCreated = 1_000_000;
        $importerFinished = $importerCreated + 300;
        $recommendationCreated = 2_000_000;
        $recommendationFinished = $recommendationCreated + 120;

        DB::table('jobs')->insert([
            [
                'queue' => 'importers',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $importerCreated,
                'created_at' => $importerCreated,
            ],
            [
                'queue' => 'importers',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $importerCreated,
                'created_at' => $importerCreated,
            ],
            [
                'queue' => 'recommendations',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $recommendationCreated,
                'created_at' => $recommendationCreated,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'redis',
                'queue' => 'importers',
                'payload' => '{}',
                'exception' => 'Example',
                'failed_at' => $now,
            ],
        ]);

        DB::table('job_batches')->insert([
            [
                'id' => (string) Str::uuid(),
                'name' => 'importer batch',
                'total_jobs' => 10,
                'pending_jobs' => 0,
                'failed_jobs' => 1,
                'failed_job_ids' => json_encode([]),
                'options' => json_encode(['queue' => 'importers']),
                'cancelled_at' => null,
                'created_at' => $importerCreated,
                'finished_at' => $importerFinished,
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'recommendation batch',
                'total_jobs' => 5,
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'failed_job_ids' => json_encode([]),
                'options' => json_encode(['queue' => 'recommendations']),
                'cancelled_at' => null,
                'created_at' => $recommendationCreated,
                'finished_at' => $recommendationFinished,
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ingestion;

use App\Models\IngestionRun;
use App\Services\Ingestion\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('ingestion_runs');

        Schema::create('ingestion_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->date('date_key');
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('last_etag')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id', 'date_key']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ingestion_runs');

        parent::tearDown();
    }

    public function test_find_or_start_creates_ingestion_run(): void
    {
        $service = $this->app->make(IdempotencyService::class);
        $date = CarbonImmutable::create(2025, 2, 16);

        $run = $service->findOrStart('tmdb', 'movie-1', $date);

        $this->assertInstanceOf(IngestionRun::class, $run);
        $this->assertSame('tmdb', $run->source);
        $this->assertSame('movie-1', $run->external_id);
        $this->assertTrue($run->date_key->isSameDay($date));
        $this->assertDatabaseCount('ingestion_runs', 1);
    }

    public function test_find_or_start_reuses_existing_run_for_same_key(): void
    {
        $service = $this->app->make(IdempotencyService::class);
        $date = CarbonImmutable::create(2025, 2, 16);

        $first = $service->findOrStart('tmdb', 'movie-1', $date);
        $second = $service->findOrStart('tmdb', 'movie-1', $date);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('ingestion_runs', 1);
    }

    public function test_record_result_persists_metadata(): void
    {
        $service = $this->app->make(IdempotencyService::class);
        $date = CarbonImmutable::create(2025, 2, 16);

        $run = $service->findOrStart('tmdb', 'movie-1', $date);

        $headers = [
            'etag' => ['"abc123"'],
            'Last-Modified' => 'Tue, 25 Feb 2025 10:00:00 GMT',
        ];

        $payload = [
            'status' => 'ok',
            'count' => 5,
        ];

        $updated = $service->recordResult($run, $headers, $payload);

        $this->assertSame($payload, $updated->response_payload);
        $this->assertSame([
            'etag' => ['"abc123"'],
            'Last-Modified' => 'Tue, 25 Feb 2025 10:00:00 GMT',
        ], $updated->response_headers);
        $this->assertSame('"abc123"', $service->lastEtag($updated));

        $lastModified = $service->lastModifiedAt($updated);
        $this->assertInstanceOf(CarbonImmutable::class, $lastModified);
        $this->assertTrue($lastModified->equalTo(CarbonImmutable::parse('Tue, 25 Feb 2025 10:00:00 GMT')));
    }
}

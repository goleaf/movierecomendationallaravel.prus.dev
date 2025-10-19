<?php

declare(strict_types=1);

namespace Tests\Feature\Importers;

use App\Jobs\Importers\ImportMovieTranslations;
use App\Models\Movie;
use App\Services\Importers\MovieTranslationImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MovieTranslationImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_translation_job_when_service_enabled(): void
    {
        config(['services.tmdb.key' => 'key']);

        Queue::fake();

        $movie = Movie::factory()->create();

        $importer = app(MovieTranslationImporter::class);
        $batch = $importer->dispatch($movie, ['ru', 'EN']);

        $this->assertNotNull($batch);

        Queue::assertPushed(ImportMovieTranslations::class, function (ImportMovieTranslations $job) use ($movie): bool {
            return $job->movieId === $movie->id && $job->locales === ['ru', 'en'];
        });
    }

    public function test_skips_dispatch_when_service_disabled_or_locales_empty(): void
    {
        config(['services.tmdb.key' => null]);

        Queue::fake();

        $movie = Movie::factory()->create();

        $importer = app(MovieTranslationImporter::class);
        $this->assertNull($importer->dispatch($movie, ['']));

        Queue::assertNothingPushed();
    }
}

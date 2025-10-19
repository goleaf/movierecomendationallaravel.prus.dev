<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\IdempotencyRecord;
use App\Services\TmdbI18n;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbI18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        Cache::flush();
    }

    public function test_duplicate_runs_are_short_circuited_and_metadata_is_persisted(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/find/*' => Http::response([
                'movie_results' => [
                    [
                        'id' => 123,
                        'media_type' => 'movie',
                    ],
                ],
            ], 200, [
                'ETag' => 'find-etag',
                'Last-Modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
            ]),
            'https://api.themoviedb.org/3/movie/*' => Http::response([
                'title' => 'Titre FR',
                'overview' => 'Résumé FR',
            ], 200, [
                'ETag' => 'movie-etag',
                'Last-Modified' => 'Thu, 02 Jan 2025 00:00:00 GMT',
            ]),
        ]);

        $service = app(TmdbI18n::class);

        $result = $service->translationsByImdb('tt1234567', ['fr']);

        $this->assertSame([
            'title' => ['fr' => 'Titre FR'],
            'plot' => ['fr' => 'Résumé FR'],
        ], $result);

        $record = IdempotencyRecord::query()->first();
        $this->assertNotNull($record);
        $this->assertSame('tmdb.translations', $record->source);
        $this->assertSame('tt1234567', $record->external_id);
        $this->assertSame('find-etag', $record->last_etag);
        $this->assertNotNull($record->last_modified_at);

        $payload = $record->payload;
        $this->assertIsArray($payload);
        $this->assertSame('movie', $payload['type']);
        $this->assertSame(123, $payload['tmdb_id']);
        $this->assertSame(['fr' => 'Titre FR'], $payload['translations']['title']);
        $this->assertSame(['fr' => 'Résumé FR'], $payload['translations']['plot']);
        $this->assertSame([
            'etag' => 'find-etag',
            'last_modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
        ], $payload['headers']['find']);
        $this->assertSame([
            'fr' => [
                'etag' => 'movie-etag',
                'last_modified' => 'Thu, 02 Jan 2025 00:00:00 GMT',
            ],
        ], $payload['headers']['translations']);

        Cache::flush();

        Http::fake(function () {
            $this->fail('TMDB should not be called for duplicate runs.');
        });

        $second = $service->translationsByImdb('tt1234567', ['fr']);

        $this->assertSame($result, $second);
        Http::assertNothingSent();
    }

    public function test_force_flag_bypasses_idempotency_run(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/find/*' => Http::response([
                'movie_results' => [
                    [
                        'id' => 123,
                        'media_type' => 'movie',
                    ],
                ],
            ], 200, [
                'ETag' => 'initial-etag',
                'Last-Modified' => 'Wed, 01 Jan 2025 00:00:00 GMT',
            ]),
            'https://api.themoviedb.org/3/movie/*' => Http::response([
                'title' => 'Titre FR',
                'overview' => 'Résumé FR',
            ], 200, [
                'ETag' => 'initial-movie-etag',
                'Last-Modified' => 'Thu, 02 Jan 2025 00:00:00 GMT',
            ]),
        ]);

        $service = app(TmdbI18n::class);
        $service->translationsByImdb('tt1234567', ['fr']);

        Cache::flush();

        $count = 0;

        Http::fake(function ($request) use (&$count) {
            $count++;

            if (str_contains($request->url(), '/find/')) {
                return Http::response([
                    'movie_results' => [
                        [
                            'id' => 123,
                            'media_type' => 'movie',
                        ],
                    ],
                ], 200, [
                    'ETag' => 'forced-etag',
                    'Last-Modified' => 'Fri, 03 Jan 2025 00:00:00 GMT',
                ]);
            }

            return Http::response([
                'title' => 'Titre MAJ',
                'overview' => 'Résumé MAJ',
            ], 200, [
                'ETag' => 'forced-movie-etag',
                'Last-Modified' => 'Sat, 04 Jan 2025 00:00:00 GMT',
            ]);
        });

        $result = $service->translationsByImdb('tt1234567', ['fr'], true);

        $this->assertSame([
            'title' => ['fr' => 'Titre MAJ'],
            'plot' => ['fr' => 'Résumé MAJ'],
        ], $result);

        $this->assertSame(2, $count);

        $record = IdempotencyRecord::query()->first();
        $this->assertNotNull($record);
        $record = $record->fresh();
        $this->assertSame('forced-etag', $record->last_etag);

        $payload = $record->payload;
        $this->assertSame('Titre MAJ', $payload['translations']['title']['fr']);
        $this->assertSame('forced-movie-etag', $payload['headers']['translations']['fr']['etag']);
    }
}

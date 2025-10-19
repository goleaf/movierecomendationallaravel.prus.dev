<?php

declare(strict_types=1);

namespace Tests\Unit;

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

        config(['cache.default' => 'array']);
        Cache::clear();
    }

    public function test_translations_by_imdb_uses_http_and_maps_payload(): void
    {
        config(['services.tmdb.key' => 'test-key']);

        Http::fakeSequence()
            ->push([
                'movie_results' => [
                    ['id' => 1234, 'media_type' => 'movie'],
                ],
            ], 200)
            ->push([
                'title' => 'Локализованный тайтл',
                'overview' => 'Описание фильма',
            ], 200);

        $service = app(TmdbI18n::class);
        $result = $service->translationsByImdb('tt9999999', ['ru']);

        $this->assertSame([
            'title' => ['ru' => 'Локализованный тайтл'],
            'plot' => ['ru' => 'Описание фильма'],
        ], $result);

        Http::assertSentCount(2);
    }

    public function test_returns_null_when_service_disabled(): void
    {
        config(['services.tmdb.key' => null]);

        Http::fake();

        $service = app(TmdbI18n::class);
        $this->assertNull($service->translationsByImdb('tt0000001', ['ru']));

        Http::assertNothingSent();
    }
}

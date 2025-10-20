<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SsrMetricsNormalizer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SsrMetricsNormalizerTest extends TestCase
{
    public function test_normalize_sanitizes_payload_and_movie_metadata(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');

        $normalizer = new SsrMetricsNormalizer;

        $payload = [
            'path' => 'movies/metric-movie',
            'score' => 110,
            'html_size' => '4096',
            'meta_count' => '12',
            'og_count' => '4',
            'ldjson_count' => '0',
            'img_count' => '6',
            'blocking_scripts' => '2',
            'first_byte_ms' => '150',
            'recorded_at' => '2025-01-01T11:59:30Z',
            'movie' => [
                'id' => '404',
                'title' => 'Metric Movie',
                'slug' => 'metric-movie',
                'imdb_tt' => 'tt6677889',
                'year' => '2024',
                'release_date' => '2024-01-05',
                'poster_url' => 'https://images.test/poster.jpg',
                'imdb_rating' => '7.5',
                'imdb_votes' => '9000',
                'runtime_min' => '118',
                'type' => 'movie',
                'genres' => ['Drama', 'Sci-Fi'],
                'unused' => 'ignore-me',
            ],
        ];

        $normalized = $normalizer->normalize($payload);

        $this->assertSame('/movies/metric-movie', $normalized['path']);
        $this->assertSame(100, $normalized['score']);
        $this->assertSame(4096, $normalized['html_bytes']);
        $this->assertSame(12, $normalized['meta_count']);
        $this->assertSame(4, $normalized['og_count']);
        $this->assertSame(0, $normalized['ldjson_count']);
        $this->assertSame(6, $normalized['img_count']);
        $this->assertSame(2, $normalized['blocking_scripts']);
        $this->assertSame(150, $normalized['first_byte_ms']);
        $this->assertFalse($normalized['has_json_ld']);
        $this->assertTrue($normalized['has_open_graph']);
        $this->assertSame('2025-01-01T11:59:30+00:00', $normalized['recorded_at']->toIso8601String());

        $this->assertSame([
            'id' => 404,
            'title' => 'Metric Movie',
            'slug' => 'metric-movie',
            'imdb_tt' => 'tt6677889',
            'release_year' => 2024,
            'release_date' => '2024-01-05',
            'poster_url' => 'https://images.test/poster.jpg',
            'imdb_rating' => 7.5,
            'imdb_votes' => 9000,
            'runtime_min' => 118,
            'type' => 'movie',
            'genres' => ['Drama', 'Sci-Fi'],
        ], $normalized['movie']);

        $this->assertSame($normalized['movie'], $normalized['meta']['movie']);
        $this->assertSame(4096, $normalized['meta']['html_bytes']);
        $this->assertSame(4096, $normalized['meta']['html_size']);
        $this->assertSame(12, $normalized['meta']['meta_count']);
    }
}

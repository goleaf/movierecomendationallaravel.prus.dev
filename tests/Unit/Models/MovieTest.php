<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Movie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Movie::class)]
final class MovieTest extends TestCase
{
    public function test_genre_synonyms_are_normalized(): void
    {
        $movie = new Movie;

        $movie->genres = ['Sci-Fi', 'Action & Adventure', 'romcom', 'Documentary Series'];

        self::assertSame(
            ['science fiction', 'action', 'adventure', 'romance', 'comedy', 'documentary'],
            $movie->genres?->values()->all(),
        );
    }

    public function test_genre_string_payload_is_split_and_trimmed(): void
    {
        $movie = new Movie;

        $movie->genres = ' Science Fiction;Thriller|Kids ';

        self::assertSame(
            ['science fiction', 'thriller', 'family'],
            $movie->genres?->values()->all(),
        );
    }

    public function test_empty_genres_become_null(): void
    {
        $movie = new Movie;

        $movie->genres = [' ', null, ''];

        self::assertNull($movie->genres);
    }
}

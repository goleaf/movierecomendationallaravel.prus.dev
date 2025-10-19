<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use PHPUnit\Framework\TestCase;

class MovieGenreNormalizationTest extends TestCase
{
    public function test_genre_synonyms_and_splitting_are_normalized(): void
    {
        $movie = new Movie;
        $movie->genres = 'Sci Fi & Fantasy;Action-Adventure, Drama';

        $this->assertSame([
            'science fiction',
            'fantasy',
            'action',
            'adventure',
            'drama',
        ], $movie->genres);
    }

    public function test_duplicate_and_empty_genres_removed(): void
    {
        $movie = new Movie;
        $movie->genres = ['RomCom', 'romantic comedy', '  ', 'Comedy'];

        $this->assertSame([
            'romance',
            'comedy',
        ], $movie->genres);
    }
}

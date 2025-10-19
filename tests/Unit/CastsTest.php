<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

use function collect;

final class CastsTest extends TestCase
{
    public function test_genres_cast_normalizes_and_serializes_arrays(): void
    {
        $movie = new Movie;

        $movie->genres = ['Sci-Fi', 'Action & Adventure', 'romcom'];

        self::assertInstanceOf(Collection::class, $movie->genres);
        self::assertSame([
            'science fiction',
            'action',
            'adventure',
            'romance',
            'comedy',
        ], $movie->genres->values()->all());
        self::assertSame(
            json_encode([
                'science fiction',
                'action',
                'adventure',
                'romance',
                'comedy',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $movie->getAttributes()['genres'] ?? null,
        );
    }

    public function test_genres_cast_accepts_collection_and_decodes_json_payloads(): void
    {
        $movie = new Movie;

        $movie->genres = collect(['Documentary Series', 'Drama']);

        self::assertSame(['documentary', 'drama'], $movie->genres?->values()->all());

        $movieWithRawGenres = new Movie;
        $movieWithRawGenres->setRawAttributes(['genres' => ' ["mystery" , "thriller" ] '], true);

        self::assertInstanceOf(Collection::class, $movieWithRawGenres->genres);
        self::assertSame(['mystery', 'thriller'], $movieWithRawGenres->genres->values()->all());
    }

    public function test_genres_cast_rejects_non_string_values(): void
    {
        $movie = new Movie;

        $this->expectException(ValidationException::class);

        $movie->genres = ['valid', 42];
    }

    public function test_release_date_cast_normalizes_strings_to_iso_dates(): void
    {
        $movie = new Movie;

        $movie->release_date = '2024-05-01';

        self::assertInstanceOf(CarbonImmutable::class, $movie->release_date);
        self::assertSame('2024-05-01', $movie->release_date?->toDateString());
        self::assertSame('2024-05-01', $movie->getAttributes()['release_date'] ?? null);
    }

    public function test_release_date_cast_accepts_carbon_instances(): void
    {
        $movie = new Movie;
        $date = CarbonImmutable::create(2024, 5, 1, 15, 30);

        $movie->release_date = $date;

        self::assertTrue($movie->release_date?->isSameDay($date));
        self::assertSame('00:00:00', $movie->release_date?->format('H:i:s'));
        self::assertSame('2024-05-01', $movie->getAttributes()['release_date'] ?? null);
    }

    public function test_release_date_cast_parses_raw_database_values(): void
    {
        $movie = new Movie;
        $movie->setRawAttributes(['release_date' => '2024-05-01 11:22:33'], true);

        self::assertInstanceOf(CarbonImmutable::class, $movie->release_date);
        self::assertSame('2024-05-01', $movie->release_date?->toDateString());
        self::assertSame('00:00:00', $movie->release_date?->format('H:i:s'));
    }

    public function test_release_date_cast_rejects_invalid_values(): void
    {
        $movie = new Movie;

        $this->expectException(ValidationException::class);

        $movie->release_date = 'not-a-date';
    }
}

<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

use function sprintf;

/**
 * @implements CastsAttributes<Collection<int, string>, Collection<int, string>|null>
 */
final class GenresCast implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    /**
     * Map of normalized external genre labels to local canonical genres.
     *
     * @var array<string, array<int, string>>
     */
    private const GENRE_SYNONYMS = [
        'sci fi' => ['science fiction'],
        'scifi' => ['science fiction'],
        'sciencefiction' => ['science fiction'],
        'science fiction & fantasy' => ['science fiction', 'fantasy'],
        'science fiction fantasy' => ['science fiction', 'fantasy'],
        'sci fi & fantasy' => ['science fiction', 'fantasy'],
        'sci fi fantasy' => ['science fiction', 'fantasy'],
        'scifi & fantasy' => ['science fiction', 'fantasy'],
        'scifi fantasy' => ['science fiction', 'fantasy'],
        'scififantasy' => ['science fiction', 'fantasy'],
        'sciencefictionfantasy' => ['science fiction', 'fantasy'],
        'romantic comedy' => ['romance', 'comedy'],
        'romcom' => ['romance', 'comedy'],
        'rom com' => ['romance', 'comedy'],
        'action adventure' => ['action', 'adventure'],
        'action & adventure' => ['action', 'adventure'],
        'action adventure fiction' => ['action', 'adventure'],
        'actionadventure' => ['action', 'adventure'],
        'kids' => ['family'],
        'children' => ['family'],
        'childrens' => ['family'],
        'family friendly' => ['family'],
        'biopic' => ['biography', 'drama'],
        'biographical' => ['biography'],
        'film noir' => ['noir'],
        'docu series' => ['documentary'],
        'docuseries' => ['documentary'],
        'documentary series' => ['documentary'],
        'tv movie' => ['tv movie'],
        'television movie' => ['tv movie'],
        'tv special' => ['tv movie'],
        'mini series' => ['miniseries'],
    ];

    /**
     * @return Collection<int, string>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Collection
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    $key => sprintf('The %s field must be an array.', $key),
                ]);
            }
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([
                $key => sprintf('The %s field must be an array.', $key),
            ]);
        }

        return collect($this->normalizeGenres($value, $key));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_string($value) || is_array($value)) {
            $normalized = $this->normalizeGenres($value, $key);

            if ($normalized === []) {
                return null;
            }

            return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        throw ValidationException::withMessages([
            $key => sprintf('The %s field must be an array.', $key),
        ]);
    }

    /**
     * @param  array<int, string>|string  $value
     * @return array<int, string>
     */
    private function normalizeGenres(array|string $value, string $key): array
    {
        $genres = $this->toGenreArray($value);

        if ($genres === []) {
            return [];
        }

        $normalized = [];

        foreach ($genres as $genre) {
            if (! is_string($genre)) {
                throw ValidationException::withMessages([
                    $key => sprintf('The %s field must be a string.', $key),
                ]);
            }

            foreach ($this->mapGenre($genre) as $mapped) {
                if ($mapped === '') {
                    continue;
                }

                if (! in_array($mapped, $normalized, true)) {
                    $normalized[] = $mapped;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>|string  $value
     * @return array<int, string>
     */
    private function toGenreArray(array|string $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $parts = preg_split('/[,;|]/', $value) ?: [];

        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array<int, string>
     */
    private function mapGenre(string $genre): array
    {
        $normalized = Str::of($genre)
            ->lower()
            ->replaceMatches('/[_.\\/]/', ' ')
            ->replace('-', ' ')
            ->replace('&', ' & ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return [];
        }

        if (array_key_exists($normalized, self::GENRE_SYNONYMS)) {
            return self::GENRE_SYNONYMS[$normalized];
        }

        return [$normalized];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Importers;

use App\Jobs\Importers\ImportMovieTranslations;
use App\Models\Movie;
use App\Services\TmdbI18n;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class MovieTranslationImporter
{
    public function __construct(private readonly TmdbI18n $tmdb) {}

    /**
     * @param  array<int, string>  $locales
     */
    public function dispatch(Movie $movie, array $locales, bool $force = false): ?Batch
    {
        if (! $this->tmdb->enabled()) {
            return null;
        }

        $normalized = $this->normalizeLocales($locales);

        if ($normalized === []) {
            return null;
        }

        return Bus::batch([
            new ImportMovieTranslations($movie->id, $normalized, $force),
        ])
            ->name(sprintf('movie-%d-translations', $movie->id))
            ->onQueue('importers')
            ->allowFailures()
            ->dispatch();
    }

    /**
     * @param  array<int, string>  $locales
     * @return array<int, string>
     */
    private function normalizeLocales(array $locales): array
    {
        $normalized = [];

        foreach ($locales as $locale) {
            $value = strtolower(trim((string) $locale));

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}

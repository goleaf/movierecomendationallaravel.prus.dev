<?php

declare(strict_types=1);

namespace App\Jobs\Importers;

use App\Models\Movie;
use App\Services\TmdbI18n;
use App\Support\TranslationPayload;
use Carbon\CarbonInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportMovieTranslations implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $locales
     */
    public function __construct(
        public int $movieId,
        public array $locales,
    ) {
        $this->onQueue('importers');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function retryUntil(): CarbonInterface
    {
        return now()->addHours(6);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'importer',
            "movie:{$this->movieId}",
        ];
    }

    public function handle(TmdbI18n $tmdb): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! $tmdb->enabled()) {
            Log::channel('importers')->warning('TMDB translations importer skipped: service disabled.', [
                'movie_id' => $this->movieId,
                'locales' => $this->locales,
            ]);

            return;
        }

        $movie = Movie::query()->find($this->movieId);

        if ($movie === null) {
            Log::channel('importers')->warning('Movie record missing for translation import.', [
                'movie_id' => $this->movieId,
                'locales' => $this->locales,
            ]);

            return;
        }

        $locales = $this->normalizeLocales($this->locales);

        if ($locales === []) {
            return;
        }

        $translations = $tmdb->translationsByImdb($movie->imdb_tt, $locales);

        if ($translations === null) {
            Log::channel('importers')->info('TMDB returned no translations for requested locales.', [
                'movie_id' => $this->movieId,
                'locales' => $locales,
            ]);

            return;
        }

        $merged = TranslationPayload::merge($movie->translations, $translations);

        if ($merged !== null) {
            $movie->translations = $merged;
            $movie->save();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('importers')->error('Movie translation import job failed.', [
            'movie_id' => $this->movieId,
            'locales' => $this->locales,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
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

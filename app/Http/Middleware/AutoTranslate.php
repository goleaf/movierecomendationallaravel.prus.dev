<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Movie;
use App\Services\TmdbI18n;
use App\Support\TranslationPayload;
use Closure;
use Illuminate\Http\Request;

class AutoTranslate
{
    public function __construct(protected TmdbI18n $i18n) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $movie = $request->route('movie');
        if ($movie instanceof Movie && $this->i18n->enabled()) {
            $langs = $this->parse($request->header('Accept-Language', ''));
            $supported = array_keys(config('filament-translation-component.languages', []));
            $existing = TranslationPayload::normalize($movie->translations);
            $need = [];

            foreach (array_slice($langs, 0, 5) as $locale) {
                $resolved = $this->resolveLocale($locale, $supported);

                if ($resolved === null) {
                    continue;
                }

                if (($existing['title'][$resolved] ?? null) === null && ($existing['plot'][$resolved] ?? null) === null) {
                    $need[] = $resolved;
                }
            }

            if ($need) {
                $need = array_values(array_unique($need));
                try {
                    $map = $this->i18n->translationsByImdb($movie->imdb_tt, $need);

                    if (! empty($map)) {
                        $movie->translations = TranslationPayload::merge($movie->translations, $map);
                        $movie->save();
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return $response;
    }

    protected function parse(string $h): array
    {
        $out = [];
        foreach (array_map('trim', explode(',', $h)) as $p) {
            $seg = strtolower(explode(';', $p)[0] ?? '');
            if ($seg !== '') {
                $out[] = $seg;
            }
        }

        return array_unique($out);
    }

    /**
     * @param  array<int, string>  $supported
     */
    protected function resolveLocale(string $locale, array $supported): ?string
    {
        if ($supported === []) {
            return $locale;
        }

        if (in_array($locale, $supported, true)) {
            return $locale;
        }

        if (str_contains($locale, '-')) {
            $base = explode('-', $locale, 2)[0];

            if (in_array($base, $supported, true)) {
                return $base;
            }
        }

        return null;
    }
}

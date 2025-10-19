<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Http\Policy;
use App\Support\TranslationPayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TmdbI18n
{
    private const SOURCE = 'tmdb.translations';

    protected ?string $apiKey;

    protected string $base = 'https://api.themoviedb.org/3';

    protected int $cacheTtl;

    public function __construct(private readonly IdempotencyService $idempotency)
    {
        $this->apiKey = config('services.tmdb.key', env('TMDB_API_KEY'));
        $this->cacheTtl = max(1, (int) config('services.tmdb.cache_ttl', 3600));
    }

    public function enabled(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @param  array<int, string>  $langs
     * @return array{title: array<string, string>, plot: array<string, string>}|null
     */
    public function translationsByImdb(string $imdbId, array $langs = [], bool $force = false): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $locales = $this->normalizeLocales($langs);
        $dateKey = today()->toDateString();
        $existingRun = $this->idempotency->find(self::SOURCE, $imdbId, $dateKey);

        if (! $force && $this->idempotency->shouldShortCircuit($existingRun, $locales)) {
            $payload = $existingRun?->payload;
            $translations = is_array($payload) ? ($payload['translations'] ?? null) : null;

            return TranslationPayload::prepare($translations);
        }

        $resp = Policy::external()->get("{$this->base}/find/{$imdbId}", [
            'api_key' => $this->apiKey,
            'external_source' => 'imdb_id',
        ]);

        if ($resp->failed()) {
            return null;
        }

        $findEtag = $resp->header('ETag');
        $findLastModified = $resp->header('Last-Modified');

        $j = $resp->json() ?? [];
        $obj = ($j['movie_results'][0] ?? null) ?? ($j['tv_results'][0] ?? null);

        if (! $obj) {
            return null;
        }

        $type = $obj['media_type'] ?? (isset($obj['first_air_date']) ? 'tv' : 'movie');
        $id = (int) ($obj['id'] ?? 0);

        if ($id === 0) {
            return null;
        }

        $out = [
            'title' => [],
            'plot' => [],
        ];
        $translationHeaders = [];

        foreach ($locales as $locale) {
            $tr = $this->one($type, $id, $locale);

            if (! is_array($tr)) {
                continue;
            }

            $meta = is_array($tr['meta'] ?? null) ? $tr['meta'] : [];

            if ($meta !== []) {
                $translationHeaders[$locale] = [
                    'etag' => $meta['etag'] ?? null,
                    'last_modified' => $meta['last_modified'] ?? null,
                ];
            }

            $title = $tr['title'] ?? null;
            $plot = $tr['plot'] ?? null;

            if (is_string($title) && $title !== '') {
                $out['title'][$locale] = $title;
            }

            if (is_string($plot) && $plot !== '') {
                $out['plot'][$locale] = $plot;
            }
        }

        $normalizedTranslations = TranslationPayload::normalize($out);
        $existingPayload = is_array($existingRun?->payload) ? $existingRun->payload : [];

        if ($existingPayload !== []) {
            $normalizedTranslations = TranslationPayload::merge($existingPayload['translations'] ?? null, $normalizedTranslations)
                ?? $normalizedTranslations;
            $existingTranslationHeaders = $existingPayload['headers']['translations'] ?? [];

            if (is_array($existingTranslationHeaders)) {
                $translationHeaders = array_replace($existingTranslationHeaders, $translationHeaders);
            }

            $existingFindHeaders = $existingPayload['headers']['find'] ?? [];

            if (is_array($existingFindHeaders)) {
                $findEtag = $findEtag ?? ($existingFindHeaders['etag'] ?? null);
                $findLastModified = $findLastModified ?? ($existingFindHeaders['last_modified'] ?? null);
            }

            $type = $existingPayload['type'] ?? $type;
            $id = (int) ($existingPayload['tmdb_id'] ?? $id);
        }

        $payload = [
            'type' => $type,
            'tmdb_id' => $id,
            'translations' => $normalizedTranslations,
            'headers' => [
                'find' => [
                    'etag' => $findEtag,
                    'last_modified' => $findLastModified,
                ],
                'translations' => $translationHeaders,
            ],
        ];

        $lastModifiedAt = $this->parseLastModified($findLastModified) ?? $existingRun?->last_modified_at;
        $lastEtag = $findEtag ?? $existingRun?->last_etag;

        $this->idempotency->persist(
            self::SOURCE,
            $imdbId,
            $dateKey,
            $lastEtag,
            $lastModifiedAt,
            $payload,
        );

        return TranslationPayload::prepare($payload['translations']);
    }

    /**
     * @return array{title:string|null,plot:string|null,meta:array<string, string|null>}|null
     */
    protected function one(string $type, int $id, string $lang): ?array
    {
        $key = $this->cacheKey($type, $id, $lang);
        $payload = Cache::remember($key, now()->addSeconds($this->cacheTtl), function () use ($type, $id, $lang): array {
            $path = $type === 'tv' ? "tv/{$id}" : "movie/{$id}";
            $resp = Policy::external()->get("{$this->base}/{$path}", [
                'api_key' => $this->apiKey,
                'language' => $lang,
            ]);

            $meta = [
                'etag' => $resp->header('ETag'),
                'last_modified' => $resp->header('Last-Modified'),
            ];

            if ($resp->failed()) {
                return ['meta' => $meta];
            }

            $o = $resp->json();
            $title = $o['title'] ?? ($o['name'] ?? null);
            $overview = $o['overview'] ?? null;

            return [
                'title' => is_string($title) ? $title : null,
                'plot' => is_string($overview) ? $overview : null,
                'meta' => $meta,
            ];
        });

        if ($payload === []) {
            return null;
        }

        return $payload;
    }

    protected function cacheKey(string $type, int $id, string $lang): string
    {
        return sprintf('tmdb:%s:%d:%s', $type, $id, $lang);
    }

    /**
     * @param  array<int, string>  $langs
     * @return array<int, string>
     */
    protected function normalizeLocales(array $langs): array
    {
        $normalized = [];

        foreach ($langs as $lang) {
            $value = strtolower(trim((string) $lang));

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function parseLastModified(?string $header): ?CarbonInterface
    {
        if ($header === null || $header === '') {
            return null;
        }

        try {
            return Carbon::parse($header);
        } catch (Throwable) {
            return null;
        }
    }
}

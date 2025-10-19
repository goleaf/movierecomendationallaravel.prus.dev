<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Ingestion\IdempotencyService;
use App\Support\TranslationPayload;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TmdbI18n
{
    protected ?string $apiKey;

    protected string $base = 'https://api.themoviedb.org/3';

    /**
     * @var array<int, string>
     */
    protected array $sourceKey = ['tmdb.translations'];

    public function __construct(protected IdempotencyService $idempotency)
    {
        $this->apiKey = config('services.tmdb.key', env('TMDB_API_KEY'));
    }

    public function enabled(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @param  array<int, string>  $langs
     * @return array{title: array<string, string>, plot: array<string, string>}|null
     */
    public function translationsByImdb(string $imdbId, array $langs = []): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $today = CarbonImmutable::today();
        $languageSources = $this->languageSources($langs);
        $sources = array_merge($this->sourceKey, $languageSources);

        if ($this->idempotency->shouldSkip($sources, $imdbId, $today)) {
            return null;
        }

        $previous = $this->idempotency->latest($sources, $imdbId);

        if ($previous === null && $languageSources !== []) {
            $previous = $this->idempotency->latest($this->sourceKey, $imdbId);
        }

        $conditionalHeaders = $this->buildConditionalHeaders(
            $previous?->last_etag,
            $previous?->last_modified
        );

        $resp = $this->request($conditionalHeaders)->get("{$this->base}/find/{$imdbId}", [
            'api_key' => $this->apiKey,
            'external_source' => 'imdb_id',
        ]);

        if ($resp->status() === 304) {
            $this->idempotency->record(
                $sources,
                $imdbId,
                $today,
                $previous?->last_etag,
                $previous?->last_modified
            );

            return null;
        }

        if ($resp->failed()) {
            return null;
        }

        $metadata = [
            'last_etag' => $this->extractHeader($resp, 'ETag') ?? $previous?->last_etag,
            'last_modified' => $this->parseLastModified($resp->header('Last-Modified')) ?? $previous?->last_modified,
        ];

        $j = $resp->json() ?? [];
        $obj = ($j['movie_results'][0] ?? null) ?? ($j['tv_results'][0] ?? null);
        if (! $obj) {
            $this->idempotency->record(
                $sources,
                $imdbId,
                $today,
                $metadata['last_etag'],
                $metadata['last_modified'] instanceof CarbonImmutable ? $metadata['last_modified'] : null
            );

            return null;
        }
        $type = ($obj['media_type'] ?? (isset($obj['first_air_date']) ? 'tv' : 'movie'));
        $id = $obj['id'];
        $out = [
            'title' => [],
            'plot' => [],
        ];
        foreach ($langs as $lang) {
            $tr = $this->one($type, (int) $id, $lang, $metadata);
            if (! $tr) {
                continue;
            }
            $title = $tr['title'] ?? null;
            $plot = $tr['plot'] ?? null;
            if (is_string($title) && $title !== '') {
                $out['title'][$lang] = $title;
            }
            if (is_string($plot) && $plot !== '') {
                $out['plot'][$lang] = $plot;
            }
        }

        $prepared = TranslationPayload::prepare($out);

        $this->idempotency->record(
            $sources,
            $imdbId,
            $today,
            $metadata['last_etag'],
            $metadata['last_modified'] instanceof CarbonImmutable ? $metadata['last_modified'] : null
        );

        return $prepared;
    }

    /**
     * @param  array{last_etag:string|null,last_modified:\Carbon\CarbonImmutable|null}  $metadata
     * @return array{title:string|null,plot:string|null}|null
     */
    protected function one(string $type, int $id, string $lang, array &$metadata): ?array
    {
        $path = $type === 'tv' ? "tv/{$id}" : "movie/{$id}";
        $resp = Http::timeout(20)->get("{$this->base}/{$path}", [
            'api_key' => $this->apiKey,
            'language' => $lang,
        ]);
        if ($resp->failed()) {
            return null;
        }

        $this->captureMetadata($resp, $metadata);
        $o = $resp->json();
        $title = $o['title'] ?? ($o['name'] ?? null);
        $overview = $o['overview'] ?? null;
        if (! $title && ! $overview) {
            return null;
        }

        return ['title' => $title, 'plot' => $overview];
    }

    /**
     * @return array<string, string>
     */
    protected function buildConditionalHeaders(?string $etag, ?CarbonImmutable $lastModified): array
    {
        $headers = [];

        if (is_string($etag) && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        if ($lastModified instanceof CarbonImmutable) {
            $headers['If-Modified-Since'] = $lastModified->toRfc7231String();
        }

        return $headers;
    }

    protected function request(array $headers = []): PendingRequest
    {
        $request = Http::timeout(20);

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    protected function captureMetadata(Response $response, array &$metadata): void
    {
        $etag = $this->extractHeader($response, 'ETag');
        if ($etag !== null && (! isset($metadata['last_etag']) || $metadata['last_etag'] === null)) {
            $metadata['last_etag'] = $etag;
        }

        $lastModified = $this->parseLastModified($response->header('Last-Modified'));
        if ($lastModified !== null && (! isset($metadata['last_modified']) || $metadata['last_modified'] === null)) {
            $metadata['last_modified'] = $lastModified;
        }
    }

    protected function extractHeader(Response $response, string $header): ?string
    {
        $value = $response->header($header);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function parseLastModified(?string $header): ?CarbonImmutable
    {
        if ($header === null || $header === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($header);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * @param  array<int, string>  $langs
     * @return array<int, string>
     */
    protected function languageSources(array $langs): array
    {
        $sources = [];

        foreach ($langs as $lang) {
            if (! is_string($lang) || $lang === '') {
                continue;
            }

            $normalized = 'lang:'.mb_strtolower($lang);
            $sources[$normalized] = $normalized;
        }

        return array_values($sources);
    }
}

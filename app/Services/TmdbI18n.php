<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\TranslationPayload;
use Illuminate\Support\Facades\Http;

class TmdbI18n
{
    protected ?string $apiKey;

    protected string $base = 'https://api.themoviedb.org/3';

    public function __construct()
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
        $resp = Http::timeout(20)->get("{$this->base}/find/{$imdbId}", [
            'api_key' => $this->apiKey,
            'external_source' => 'imdb_id',
        ]);
        if ($resp->failed()) {
            return null;
        }
        $j = $resp->json() ?? [];
        $obj = ($j['movie_results'][0] ?? null) ?? ($j['tv_results'][0] ?? null);
        if (! $obj) {
            return null;
        }
        $type = ($obj['media_type'] ?? (isset($obj['first_air_date']) ? 'tv' : 'movie'));
        $id = $obj['id'];
        $out = [
            'title' => [],
            'plot' => [],
        ];
        foreach ($langs as $lang) {
            $tr = $this->one($type, (int) $id, $lang);
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

        return TranslationPayload::prepare($out);
    }

    /**
     * @return array{title:string|null,plot:string|null}|null
     */
    protected function one(string $type, int $id, string $lang): ?array
    {
        $path = $type === 'tv' ? "tv/{$id}" : "movie/{$id}";
        $resp = Http::timeout(20)->get("{$this->base}/{$path}", [
            'api_key' => $this->apiKey,
            'language' => $lang,
        ]);
        if ($resp->failed()) {
            return null;
        }
        $o = $resp->json();
        $title = $o['title'] ?? ($o['name'] ?? null);
        $overview = $o['overview'] ?? null;
        if (! $title && ! $overview) {
            return null;
        }

        return ['title' => $title, 'plot' => $overview];
    }
}

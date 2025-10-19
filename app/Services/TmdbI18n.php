<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\MovieApis\TmdbClient;
use App\Support\TranslationPayload;

class TmdbI18n
{
    public function __construct(protected TmdbClient $client) {}

    public function enabled(): bool
    {
        return $this->client->enabled();
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
        $resp = $this->client->get("find/{$imdbId}", [
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
        $resp = $this->client->get($path, [
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

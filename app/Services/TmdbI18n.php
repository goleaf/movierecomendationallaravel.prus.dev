<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TmdbI18n
{
    protected string $apiKey;
    protected string $base='https://api.themoviedb.org/3';
    public function __construct(){ $this->apiKey = config('services.tmdb.key', env('TMDB_API_KEY')); }
    public function enabled(): bool { return !empty($this->apiKey); }

    /** @param array<int,string> $langs */
    public function translationsByImdb(string $imdbId, array $langs=[]): array
    {
        if(!$this->enabled()) return [];
        $resp = Http::timeout(20)->get("{$this->base}/find/{$imdbId}", ['api_key'=>$this->apiKey,'external_source'=>'imdb_id']);
        if($resp->failed()) return [];
        $j=$resp->json() ?? [];
        $obj = ($j['movie_results'][0] ?? null) ?? ($j['tv_results'][0] ?? null);
        if(!$obj) return [];
        $type = ($obj['media_type'] ?? (isset($obj['first_air_date'])?'tv':'movie'));
        $id = $obj['id'];
        $out=[];
        foreach($langs as $lang){ $tr=$this->one($type,(int)$id,$lang); if($tr) $out[$lang]=$tr; }
        return $out;
    }
    protected function one(string $type,int $id,string $lang): ?array
    {
        $path=$type==='tv'?"tv/{$id}":"movie/{$id}";
        $resp = Http::timeout(20)->get("{$this->base}/{$path}", ['api_key'=>$this->apiKey,'language'=>$lang]);
        if($resp->failed()) return null;
        $o=$resp->json(); $title=$o['title'] ?? ($o['name'] ?? null); $overview=$o['overview'] ?? null;
        if(!$title && !$overview) return null; return ['title'=>$title,'plot'=>$overview];
    }
}

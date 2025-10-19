<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Movie;
use App\Services\TmdbI18n;

class AutoTranslate
{
    public function __construct(protected TmdbI18n $i18n) {}
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $movie = $request->route('movie');
        if ($movie instanceof Movie && $this->i18n->enabled()) {
            $langs = $this->parse($request->header('Accept-Language', ''));
            $need=[]; foreach(array_slice($langs,0,5) as $lc) if(empty($movie->translations[$lc])) $need[]=$lc;
            if($need){
                try{ $map=$this->i18n->translationsByImdb($movie->imdb_tt,$need);
                    if(!empty($map)){ $movie->translations = array_merge($movie->translations ?? [], $map); $movie->save(); }
                }catch(\Throwable $e){}
            }
        }
        return $response;
    }
    protected function parse(string $h): array {
        $out=[]; foreach (array_map('trim', explode(',', $h)) as $p){ $seg=strtolower(explode(';',$p)[0]??''); if($seg!=='') $out[]=$seg; } return array_unique($out);
    }
}

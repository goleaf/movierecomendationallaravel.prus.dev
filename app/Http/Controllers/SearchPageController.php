<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;

class SearchPageController extends Controller
{
    public function __invoke(Request $r): SearchResultCollection
    {
        $q=trim((string)$r->query('q',''));
        $type=$r->query('type'); $genre=$r->query('genre');
        $yf=(int)$r->query('yf',0); $yt=(int)$r->query('yt',0);

        $query=Movie::query();
        if($q!=='') $query->where(fn($w)=>$w->where('title','like',"%$q%")->orWhere('imdb_tt',$q));
        if($type) $query->where('type',$type);
        if($genre) $query->whereJsonContains('genres',$genre);
        if($yf) $query->where('year','>=',$yf);
        if($yt) $query->where('year','<=',$yt);

        $items=$query->orderByDesc('imdb_votes')->orderByDesc('imdb_rating')->limit(40)->get();

        return new SearchResultCollection($items, [
            'q'=>$q,
            'type'=>$type,
            'genre'=>$genre,
            'yf'=>$yf,
            'yt'=>$yt,
        ]);
    }
}

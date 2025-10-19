<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $r): SearchResultCollection
    {
        $q = trim((string)$r->query('q',''));
        $type = $r->query('type'); $genre = $r->query('genre');
        $yf=(int)$r->query('yf',0); $yt=(int)$r->query('yt',0);
        $per=min(50,max(1,(int)$r->query('per',20)));

        $query=Movie::query();
        if($q!=='') $query->where(fn($w)=>$w->where('title','like',"%$q%")->orWhere('imdb_tt',$q));
        if($type) $query->where('type',$type);
        if($genre) $query->whereJsonContains('genres',$genre);
        if($yf) $query->where('year','>=',$yf);
        if($yt) $query->where('year','<=',$yt);
        $items=$query->orderByDesc('imdb_votes')->orderByDesc('imdb_rating')->limit($per)->get();
        return new SearchResultCollection($items);
    }
}

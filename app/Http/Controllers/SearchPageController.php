<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class SearchPageController extends Controller
{
    /** @return View|SearchResultCollection|JsonResponse */
    public function __invoke(Request $r)
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

        if($r->wantsJson()) return new SearchResultCollection($items);
        return view('search.index',['q'=>$q,'items'=>$items,'type'=>$type,'genre'=>$genre,'yf'=>$yf,'yt'=>$yt]);
    }
}

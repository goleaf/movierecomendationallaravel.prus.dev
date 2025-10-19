<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** @extends ResourceCollection<\App\Models\Movie> */
class SearchResultCollection extends ResourceCollection
{
    public function __construct($resource, protected array $context = [])
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    { return $this->collection->map(fn($m)=>(new SearchResultResource($m))->toArray($request))->all(); }

    public function toResponse($request)
    {
        if($request->wantsJson()) return parent::toResponse($request);

        return response()->view('search.index', ['items'=>$this->resource] + $this->context);
    }
}

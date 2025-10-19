<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** @extends ResourceCollection<\App\Models\Movie> */
class SearchResultCollection extends ResourceCollection
{
    public function toArray($request): array
    { return $this->collection->map(fn($m)=>(new SearchResultResource($m))->toArray($request))->all(); }
}

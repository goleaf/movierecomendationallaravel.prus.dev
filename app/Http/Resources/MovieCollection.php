<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** @extends ResourceCollection<\App\Models\Movie> */
class MovieCollection extends ResourceCollection
{
    public function toArray($request): array
    { return $this->collection->map(fn($m)=>(new MovieResource($m))->toArray($request))->all(); }
}

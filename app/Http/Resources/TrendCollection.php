<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** @extends ResourceCollection<array|object> */
class TrendCollection extends ResourceCollection
{
    public static $wrap = 'items';

    public function toArray($request): array
    {
        return $this->collection->map(fn ($trend) => (new TrendResource($trend))->toArray($request))->all();
    }
}

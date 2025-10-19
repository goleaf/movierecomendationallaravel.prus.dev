<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** @extends ResourceCollection<array|object> */
class SsrIssueCollection extends ResourceCollection
{
    public static $wrap = 'issues';

    public function toArray($request): array
    {
        return $this->collection->map(fn ($issue) => (new SsrIssueResource($issue))->toArray($request))->all();
    }
}

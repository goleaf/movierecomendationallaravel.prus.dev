<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

class Recommender
{
    public function __construct(protected RecAb $ab){}
    /** @return Collection<int,Movie> */
    public function recommendForDevice(string $deviceId, int $limit=12): Collection
    { [$variant,$list]=$this->ab->forDevice($deviceId,$limit); return $list; }
}

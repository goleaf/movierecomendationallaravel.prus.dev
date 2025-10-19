<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Recommender
{
    public function __construct(protected RecAb $ab, protected RecommendationLogger $logger) {}

    /** @return array{variant:string,movies:Collection<int,Movie>} */
    public function recommendForDevice(string $deviceId, int $limit = 12, string $placement = 'home'): array
    {
        [$variant, $list] = $this->ab->forDevice($deviceId, $limit);
        $this->logger->recordRecommendation($deviceId, $variant, $placement, $list);

        return ['variant' => $variant, 'movies' => $list];
    }
}

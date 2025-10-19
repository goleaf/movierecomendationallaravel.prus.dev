<?php

namespace App\Services;

class Recommender
{
    public function __construct(
        protected RecAb $ab,
        protected RecommendationLogger $logger,
    ) {
    }

    public function recommendForDevice(string $deviceId, int $limit = 12, string $placement = 'home'): Recommendation
    {
        [$variant, $list] = $this->ab->forDevice($deviceId, $limit);
        $this->logger->recordRecommendation($deviceId, $variant, $placement, $list);

        return new Recommendation($variant, $list);
    }
}

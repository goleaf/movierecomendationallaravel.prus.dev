<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use App\Support\AnalyticsCache;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RecommendationLogger
{
    public function __construct(
        protected ConnectionInterface $db,
        protected TrendsRollupService $trendsRollup,
        protected AnalyticsCache $cache,
    ) {}

    /**
     * @param  Collection<int,Movie>  $movies
     */
    public function recordRecommendation(string $deviceId, string $variant, string $placement, Collection $movies): void
    {
        $now = now();

        $recordedImpressions = false;

        if ($movies->isNotEmpty() && Schema::hasTable('rec_ab_logs')) {
            $rows = $movies
                ->values()
                ->map(function (Movie $movie, int $index) use ($deviceId, $variant, $placement, $now): array {
                    $payload = json_encode([
                        'position' => $index + 1,
                    ]);

                    return [
                        'device_id' => $deviceId,
                        'placement' => $placement,
                        'variant' => $variant,
                        'movie_id' => $movie->id,
                        'payload' => $payload === false ? null : $payload,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            if ($rows !== []) {
                $this->db->table('rec_ab_logs')->insert($rows);
                $recordedImpressions = true;
            }
        }

        $movies->values()->each(function (Movie $movie, int $index) use ($deviceId, $variant, $placement, $now): void {
            Log::info('recommendation.impression', $this->appendRequestContext([
                'event' => 'recommendation.impression',
                'device_id' => $deviceId,
                'placement' => $placement,
                'variant' => $variant,
                'ab_variant' => $variant,
                'movie_id' => $movie->id,
                'position' => $index + 1,
                'impression_at' => $now->toAtomString(),
            ]));
        });

        $this->recordPageView($deviceId, $placement, null, $now);

        if ($recordedImpressions) {
            $this->cache->flushCtr();
        }
    }

    public function recordClick(string $deviceId, string $variant, string $placement, int $movieId): void
    {
        $now = now();

        $recordedClick = false;

        if (Schema::hasTable('rec_clicks')) {
            $this->db->table('rec_clicks')->insert([
                'device_id' => $deviceId,
                'placement' => $placement,
                'variant' => $variant,
                'movie_id' => $movieId,
                'clicked_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $recordedClick = true;
            $this->trendsRollup->increment($movieId, $now);
        }

        Log::info('recommendation.click', $this->appendRequestContext([
            'event' => 'recommendation.click',
            'device_id' => $deviceId,
            'placement' => $placement,
            'variant' => $variant,
            'ab_variant' => $variant,
            'movie_id' => $movieId,
            'clicked_at' => $now->toAtomString(),
        ]));

        $this->recordPageView($deviceId, 'movie', $movieId, $now);

        if ($recordedClick) {
            $this->cache->flushCtr();
            $this->cache->flushTrends();
            $this->cache->flushTrending();
        }
    }

    public function recordPageView(string $deviceId, string $page, ?int $movieId = null, ?CarbonInterface $timestamp = null): void
    {
        if (! Schema::hasTable('device_history')) {
            return;
        }

        $timestamp ??= now();

        $this->db->table('device_history')->insert([
            'device_id' => $deviceId,
            'page' => $page,
            'movie_id' => $movieId,
            'viewed_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    protected function appendRequestContext(array $context): array
    {
        $request = request();
        if (! $request instanceof Request) {
            return $context;
        }

        $requestId = $request->attributes->get('request_id', $request->headers->get('X-Request-ID'));
        if (is_string($requestId) && $requestId !== '') {
            $context['request_id'] = $requestId;
        }

        $variant = $request->attributes->get('ab_variant');
        if (is_string($variant) && $variant !== '') {
            $context['ab_variant'] = $variant;
        }

        return $context;
    }
}

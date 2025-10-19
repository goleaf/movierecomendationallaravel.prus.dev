<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use App\Services\RecommendationLogger;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class RecommendationLoggerLoggingTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::clearResolvedInstances();
        Mockery::close();

        parent::tearDown();
    }

    public function test_record_recommendation_logs_impressions_with_context(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $trends = Mockery::mock(TrendsRollupService::class);

        Schema::shouldReceive('hasTable')->with('rec_ab_logs')->andReturnFalse();
        Schema::shouldReceive('hasTable')->with('device_history')->andReturnFalse();

        $logger = new RecommendationLogger($connection, $trends);

        $request = Request::create('/home', 'GET');
        $request->attributes->set('request_id', 'req-123');
        $request->attributes->set('ab_variant', 'B');
        $this->app->instance('request', $request);

        Log::spy();

        $movie = new Movie;
        $movie->id = 42;

        $logger->recordRecommendation('device-abc', 'B', 'home', new Collection([$movie]));

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'recommendation.impression'
                    && ($context['event'] ?? null) === 'recommendation.impression'
                    && ($context['device_id'] ?? null) === 'device-abc'
                    && ($context['placement'] ?? null) === 'home'
                    && ($context['variant'] ?? null) === 'B'
                    && ($context['ab_variant'] ?? null) === 'B'
                    && ($context['movie_id'] ?? null) === 42
                    && ($context['request_id'] ?? null) === 'req-123'
                    && is_string($context['impression_at'] ?? null);
            })
            ->once();

        $this->addToAssertionCount(1);
    }

    public function test_record_click_logs_with_context(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $trends = Mockery::mock(TrendsRollupService::class);

        Schema::shouldReceive('hasTable')->with('rec_clicks')->andReturnFalse();
        Schema::shouldReceive('hasTable')->with('device_history')->andReturnFalse();

        $logger = new RecommendationLogger($connection, $trends);

        $request = Request::create('/movies/1', 'GET');
        $request->attributes->set('request_id', 'req-click');
        $request->attributes->set('ab_variant', 'A');
        $this->app->instance('request', $request);

        Log::spy();

        $logger->recordClick('device-xyz', 'A', 'home', 99);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'recommendation.click'
                    && ($context['event'] ?? null) === 'recommendation.click'
                    && ($context['device_id'] ?? null) === 'device-xyz'
                    && ($context['variant'] ?? null) === 'A'
                    && ($context['ab_variant'] ?? null) === 'A'
                    && ($context['movie_id'] ?? null) === 99
                    && ($context['request_id'] ?? null) === 'req-click'
                    && ($context['placement'] ?? null) === 'home'
                    && is_string($context['clicked_at'] ?? null);
            })
            ->once();

        $this->addToAssertionCount(1);
    }
}

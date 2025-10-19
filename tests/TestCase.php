<?php

declare(strict_types=1);

namespace Tests;

use App\Settings\RecommendationWeightsSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.stores.redis.driver' => 'array',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     A: array<string, float>,
     *     B: array<string, float>,
     *     ab_split: array<string, float>,
     *     seed: null|string
     * }
     */
    protected function storeRecommendationWeights(array $overrides = []): array
    {
        /** @var SettingsRepository $repository */
        $repository = app(SettingsRepository::class);

        return RecommendationWeightsSettings::store($repository, $overrides);
    }

    protected function forgetRecommendationWeightProperties(string ...$properties): void
    {
        if ($properties === []) {
            return;
        }

        /** @var SettingsRepository $repository */
        $repository = app(SettingsRepository::class);

        RecommendationWeightsSettings::forget($repository, ...$properties);
    }
}

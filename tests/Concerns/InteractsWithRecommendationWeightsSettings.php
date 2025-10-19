<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Settings\RecommendationWeightsSettings;
use Illuminate\Support\Facades\Schema;

trait InteractsWithRecommendationWeightsSettings
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function updateRecommendationWeightsSettings(array $overrides): RecommendationWeightsSettings
    {
        if (! Schema::hasTable('settings')) {
            $defaults = RecommendationWeightsSettings::defaults();
            $fakeValues = array_replace_recursive($defaults, $overrides);

            return RecommendationWeightsSettings::fake($fakeValues);
        }

        /** @var RecommendationWeightsSettings $settings */
        $settings = app(RecommendationWeightsSettings::class);

        foreach ($overrides as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        return $settings;
    }
}

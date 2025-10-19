<?php

declare(strict_types=1);

use App\Settings\RecommendationWeightsSettings;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = RecommendationWeightsSettings::defaults();

        $this->migrator->add('recommendation-weights.A', $defaults['A']);
        $this->migrator->add('recommendation-weights.B', $defaults['B']);
        $this->migrator->add('recommendation-weights.ab_split', $defaults['ab_split']);
        $this->migrator->add('recommendation-weights.seed', $defaults['seed']);
    }
};

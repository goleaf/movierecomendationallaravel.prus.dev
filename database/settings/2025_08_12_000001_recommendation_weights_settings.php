<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = (array) config('recs', []);

        $this->migrator->add('recommendation-weights.variant_a_pop', (float) Arr::get($defaults, 'A.pop', 0.55));
        $this->migrator->add('recommendation-weights.variant_a_recent', (float) Arr::get($defaults, 'A.recent', 0.20));
        $this->migrator->add('recommendation-weights.variant_a_pref', (float) Arr::get($defaults, 'A.pref', 0.25));
        $this->migrator->add('recommendation-weights.variant_b_pop', (float) Arr::get($defaults, 'B.pop', 0.35));
        $this->migrator->add('recommendation-weights.variant_b_recent', (float) Arr::get($defaults, 'B.recent', 0.15));
        $this->migrator->add('recommendation-weights.variant_b_pref', (float) Arr::get($defaults, 'B.pref', 0.50));
        $this->migrator->add('recommendation-weights.ab_split_a', (float) Arr::get($defaults, 'ab_split.A', 50.0));
        $this->migrator->add('recommendation-weights.ab_split_b', (float) Arr::get($defaults, 'ab_split.B', 50.0));
    }
};

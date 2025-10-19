<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = config('recs');

        $variantA = is_array($defaults['A'] ?? null)
            ? $defaults['A']
            : ['pop' => 0.55, 'recent' => 0.20, 'pref' => 0.25];

        $variantB = is_array($defaults['B'] ?? null)
            ? $defaults['B']
            : ['pop' => 0.35, 'recent' => 0.15, 'pref' => 0.50];

        $abSplit = is_array($defaults['ab_split'] ?? null)
            ? $defaults['ab_split']
            : ['A' => 50.0, 'B' => 50.0];

        $seed = isset($defaults['seed']) && is_string($defaults['seed']) && $defaults['seed'] !== ''
            ? $defaults['seed']
            : null;

        $this->migrator->add('recommendation-weights.variant_a', $variantA);
        $this->migrator->add('recommendation-weights.variant_b', $variantB);
        $this->migrator->add('recommendation-weights.ab_split', $abSplit);
        $this->migrator->add('recommendation-weights.seed', $seed);
    }
};

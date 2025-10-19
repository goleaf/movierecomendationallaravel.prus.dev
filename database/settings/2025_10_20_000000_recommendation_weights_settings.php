<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $config = config('recs');

        $this->migrator->add('recommendation_weights.split', [
            'A' => (float) ($config['ab_split']['A'] ?? 50.0),
            'B' => (float) ($config['ab_split']['B'] ?? 50.0),
        ]);

        $this->migrator->add('recommendation_weights.variant_a', [
            'pop' => (float) ($config['A']['pop'] ?? 0.55),
            'recent' => (float) ($config['A']['recent'] ?? 0.20),
            'pref' => (float) ($config['A']['pref'] ?? 0.25),
        ]);

        $this->migrator->add('recommendation_weights.variant_b', [
            'pop' => (float) ($config['B']['pop'] ?? 0.35),
            'recent' => (float) ($config['B']['recent'] ?? 0.15),
            'pref' => (float) ($config['B']['pref'] ?? 0.50),
        ]);
    }
};

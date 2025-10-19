<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('recommendation-weights.A', config('recs.A', []));
        $this->migrator->add('recommendation-weights.B', config('recs.B', []));
        $this->migrator->add('recommendation-weights.ab_split', config('recs.ab_split', ['A' => 50.0, 'B' => 50.0]));
        $this->migrator->add('recommendation-weights.seed', config('recs.seed'));
    }
};

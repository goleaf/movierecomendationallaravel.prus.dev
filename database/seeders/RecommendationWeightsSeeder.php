<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Settings\RecommendationWeightsSettings;
use Illuminate\Database\Seeder;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

class RecommendationWeightsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SettingsRepository $repository */
        $repository = app(SettingsRepository::class);

        RecommendationWeightsSettings::store($repository);
    }
}

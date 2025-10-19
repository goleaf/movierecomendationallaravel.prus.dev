<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * @phpstan-type VariantWeights array{pop: float, recent: float, pref: float}
 * @phpstan-type AbSplit array{A: float, B: float}
 */
final class RecommendationWeightsSettings extends Settings
{
    /** @var AbSplit */
    public array $split;

    /** @var VariantWeights */
    public array $variant_a;

    /** @var VariantWeights */
    public array $variant_b;

    public static function group(): string
    {
        return 'recommendation_weights';
    }

    /**
     * @return array{split: AbSplit, variant_a: VariantWeights, variant_b: VariantWeights}
     */
    public static function defaults(): array
    {
        $config = config('recs');

        return [
            'split' => [
                'A' => (float) ($config['ab_split']['A'] ?? 50.0),
                'B' => (float) ($config['ab_split']['B'] ?? 50.0),
            ],
            'variant_a' => [
                'pop' => (float) ($config['A']['pop'] ?? 0.55),
                'recent' => (float) ($config['A']['recent'] ?? 0.20),
                'pref' => (float) ($config['A']['pref'] ?? 0.25),
            ],
            'variant_b' => [
                'pop' => (float) ($config['B']['pop'] ?? 0.35),
                'recent' => (float) ($config['B']['recent'] ?? 0.15),
                'pref' => (float) ($config['B']['pref'] ?? 0.50),
            ],
        ];
    }
}

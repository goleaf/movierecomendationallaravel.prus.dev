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
    /** @var VariantWeights */
    public array $variant_a = [];

    /** @var VariantWeights */
    public array $variant_b = [];

    /** @var AbSplit */
    public array $ab_split = [];

    public ?string $seed = null;

    public static function group(): string
    {
        return 'recommendation-weights';
    }

    /**
     * @return array{
     *     variant_a: VariantWeights,
     *     variant_b: VariantWeights,
     *     ab_split: AbSplit,
     *     seed: ?string,
     * }
     */
    public static function defaults(): array
    {
        $config = (array) config('recs');

        $variantADefaults = [
            'pop' => 0.55,
            'recent' => 0.20,
            'pref' => 0.25,
        ];

        $variantBDefaults = [
            'pop' => 0.35,
            'recent' => 0.15,
            'pref' => 0.50,
        ];

        $abSplitDefaults = [
            'A' => 50.0,
            'B' => 50.0,
        ];

        return [
            'variant_a' => self::coerceVariantWeights($config['A'] ?? $variantADefaults, $variantADefaults),
            'variant_b' => self::coerceVariantWeights($config['B'] ?? $variantBDefaults, $variantBDefaults),
            'ab_split' => self::coerceAbSplit($config['ab_split'] ?? $abSplitDefaults, $abSplitDefaults),
            'seed' => isset($config['seed']) && is_string($config['seed']) && $config['seed'] !== ''
                ? $config['seed']
                : null,
        ];
    }

    /**
     * @return VariantWeights
     */
    public function weightsForVariant(string $variant): array
    {
        return match ($variant) {
            'B' => $this->variant_b,
            default => $this->variant_a,
        };
    }

    /**
     * @param  array<string, int|float>  $weights
     * @return VariantWeights
     */
    private static function coerceVariantWeights(array $weights, array $defaults): array
    {
        return [
            'pop' => self::coerceFloat($weights['pop'] ?? $defaults['pop']),
            'recent' => self::coerceFloat($weights['recent'] ?? $defaults['recent']),
            'pref' => self::coerceFloat($weights['pref'] ?? $defaults['pref']),
        ];
    }

    /**
     * @param  array<string, int|float>  $weights
     * @return AbSplit
     */
    private static function coerceAbSplit(array $weights, array $defaults): array
    {
        return [
            'A' => self::coerceFloat($weights['A'] ?? $defaults['A']),
            'B' => self::coerceFloat($weights['B'] ?? $defaults['B']),
        ];
    }

    private static function coerceFloat(int|float $value): float
    {
        return (float) $value;
    }
}

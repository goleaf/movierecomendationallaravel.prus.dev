<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Arr;
use Spatie\LaravelSettings\Settings;

final class RecommendationWeightsSettings extends Settings
{
    public float $variant_a_pop;

    public float $variant_a_recent;

    public float $variant_a_pref;

    public float $variant_b_pop;

    public float $variant_b_recent;

    public float $variant_b_pref;

    public float $ab_split_a;

    public float $ab_split_b;

    public static function group(): string
    {
        return 'recommendation-weights';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $config = (array) config('recs', []);

        return [
            'variant_a_pop' => (float) Arr::get($config, 'A.pop', 0.55),
            'variant_a_recent' => (float) Arr::get($config, 'A.recent', 0.20),
            'variant_a_pref' => (float) Arr::get($config, 'A.pref', 0.25),
            'variant_b_pop' => (float) Arr::get($config, 'B.pop', 0.35),
            'variant_b_recent' => (float) Arr::get($config, 'B.recent', 0.15),
            'variant_b_pref' => (float) Arr::get($config, 'B.pref', 0.50),
            'ab_split_a' => (float) Arr::get($config, 'ab_split.A', 50.0),
            'ab_split_b' => (float) Arr::get($config, 'ab_split.B', 50.0),
        ];
    }

    /**
     * @return array{pop: float, recent: float, pref: float}
     */
    public function weightsFor(string $variant): array
    {
        return match (strtoupper($variant)) {
            'A' => [
                'pop' => $this->variant_a_pop,
                'recent' => $this->variant_a_recent,
                'pref' => $this->variant_a_pref,
            ],
            'B' => [
                'pop' => $this->variant_b_pop,
                'recent' => $this->variant_b_recent,
                'pref' => $this->variant_b_pref,
            ],
            default => [
                'pop' => $this->variant_a_pop,
                'recent' => $this->variant_a_recent,
                'pref' => $this->variant_a_pref,
            ],
        };
    }

    /**
     * @return array{A: float, B: float}
     */
    public function abSplit(): array
    {
        return [
            'A' => $this->ab_split_a,
            'B' => $this->ab_split_b,
        ];
    }
}

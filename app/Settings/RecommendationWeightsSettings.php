<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class RecommendationWeightsSettings extends Settings
{
    public float $variant_a_pop;

    public float $variant_a_recent;

    public float $variant_a_pref;

    public float $variant_b_pop;

    public float $variant_b_recent;

    public float $variant_b_pref;

    public static function group(): string
    {
        return 'rec_weights';
    }

    /**
     * @return array<string, float>
     */
    public function weightsForVariant(string $variant): array
    {
        return match (strtoupper($variant)) {
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
}

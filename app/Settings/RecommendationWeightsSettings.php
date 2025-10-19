<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class RecommendationWeightsSettings extends Settings
{
    /** @var array<string, float> */
    public array $A = [];

    /** @var array<string, float> */
    public array $B = [];

    /** @var array<string, float> */
    public array $ab_split = [];

    public ?string $seed;

    public static function group(): string
    {
        return 'recommendation-weights';
    }
}

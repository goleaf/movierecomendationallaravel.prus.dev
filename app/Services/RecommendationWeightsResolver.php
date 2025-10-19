<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\RecommendationWeightsSettings;
use Illuminate\Support\Arr;
use Throwable;

final class RecommendationWeightsResolver
{
    public function __construct(private RecommendationWeightsSettings $settings)
    {
    }

    /**
     * @return array{pop: float, recent: float, pref: float}
     */
    public function forVariant(string $variant): array
    {
        $normalized = $this->normalize($this->resolveFromSettings($variant));

        if ($normalized['total'] <= 0.0) {
            return $this->normalize($this->fallbackFromConfig($variant));
        }

        return Arr::except($normalized, ['total']);
    }

    /**
     * @return array{pop: float, recent: float, pref: float, total: float}
     */
    private function normalize(array $weights): array
    {
        $pop = max(0.0, (float) ($weights['pop'] ?? 0.0));
        $recent = max(0.0, (float) ($weights['recent'] ?? 0.0));
        $pref = max(0.0, (float) ($weights['pref'] ?? 0.0));
        $total = $pop + $recent + $pref;

        if ($total <= 0.0) {
            return ['pop' => 0.0, 'recent' => 0.0, 'pref' => 0.0, 'total' => 0.0];
        }

        return [
            'pop' => $pop / $total,
            'recent' => $recent / $total,
            'pref' => $pref / $total,
            'total' => 1.0,
        ];
    }

    /**
     * @return array{pop: float, recent: float, pref: float}
     */
    private function resolveFromSettings(string $variant): array
    {
        try {
            return $this->settings->weightsForVariant($variant);
        } catch (Throwable) {
            return $this->fallbackFromConfig($variant);
        }
    }

    /**
     * @return array{pop: float, recent: float, pref: float}
     */
    private function fallbackFromConfig(string $variant): array
    {
        $config = config("recs.".strtoupper($variant));
        if (is_array($config)) {
            return [
                'pop' => (float) ($config['pop'] ?? 0.0),
                'recent' => (float) ($config['recent'] ?? 0.0),
                'pref' => (float) ($config['pref'] ?? 0.0),
            ];
        }

        return [
            'pop' => 0.0,
            'recent' => 0.0,
            'pref' => 0.0,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

class SsrMetricsService
{
    /**
     * @param  array{
     *     blocking_scripts?: int|null,
     *     ldjson_count?: int|null,
     *     og_count?: int|null,
     *     html_bytes?: int|null,
     *     img_count?: int|null,
     * } $metrics
     */
    public function score(array $metrics): int
    {
        $bounds = $this->scoreBounds();
        $score = (int) ($bounds['base'] ?? 100);

        $penalties = $this->penaltyWeights();

        $blockingScripts = max(0, (int) ($metrics['blocking_scripts'] ?? 0));
        if ($blockingScripts > 0) {
            $score -= $this->blockingScriptsPenalty($blockingScripts, $penalties['blocking_scripts'] ?? []);
        }

        $ldjsonCount = max(0, (int) ($metrics['ldjson_count'] ?? 0));
        if ($ldjsonCount === 0) {
            $score -= $this->deduction($penalties['missing_ldjson'] ?? []);
        }

        $ogCount = max(0, (int) ($metrics['og_count'] ?? 0));
        $minimumOg = (int) ($penalties['low_og']['minimum'] ?? 0);
        if ($minimumOg > 0 && $ogCount < $minimumOg) {
            $score -= $this->deduction($penalties['low_og'] ?? []);
        }

        $htmlBytes = max(0, (int) ($metrics['html_bytes'] ?? 0));
        $oversizedThreshold = (int) ($penalties['oversized_html']['threshold'] ?? 0);
        if ($oversizedThreshold > 0 && $htmlBytes > $oversizedThreshold) {
            $score -= $this->deduction($penalties['oversized_html'] ?? []);
        }

        $imgCount = max(0, (int) ($metrics['img_count'] ?? 0));
        $excessImageThreshold = (int) ($penalties['excess_images']['threshold'] ?? 0);
        if ($excessImageThreshold > 0 && $imgCount > $excessImageThreshold) {
            $score -= $this->deduction($penalties['excess_images'] ?? []);
        }

        return $this->clampScore($score, $bounds);
    }

    /**
     * @return array<string, mixed>
     */
    private function penaltyWeights(): array
    {
        $weights = config('ssrmetrics.weights.penalties');
        if (is_array($weights)) {
            return $weights;
        }

        $legacy = config('ssrmetrics.penalties');
        if (is_array($legacy)) {
            return $legacy;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $bounds
     */
    private function clampScore(int $score, array $bounds): int
    {
        $min = (int) ($bounds['min'] ?? 0);
        $max = (int) ($bounds['max'] ?? 100);

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        return max($min, min($max, $score));
    }

    /**
     * @return array{base:int,min:int,max:int}
     */
    private function scoreBounds(): array
    {
        $bounds = config('ssrmetrics.weights.score', []);

        $base = (int) ($bounds['base'] ?? 100);
        $min = (int) ($bounds['min'] ?? 0);
        $max = (int) ($bounds['max'] ?? 100);

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        if ($base < $min) {
            $base = $min;
        } elseif ($base > $max) {
            $base = $max;
        }

        return [
            'base' => $base,
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function blockingScriptsPenalty(int $count, array $config): int
    {
        $perScript = max(0, (int) ($config['per_script'] ?? 5));
        $max = max(0, (int) ($config['max'] ?? 30));

        return min($max, $perScript * $count);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function deduction(array $config): int
    {
        return max(0, (int) ($config['deduction'] ?? 0));
    }
}

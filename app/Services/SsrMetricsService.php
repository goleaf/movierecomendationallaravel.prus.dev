<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

class SsrMetricsService
{
    private const DEFAULTS = [
        'enabled' => false,
        'paths' => [],
        'penalties' => [
            'blocking_scripts' => [
                'per_script' => 5,
                'max' => 30,
            ],
            'missing_ldjson' => [
                'deduction' => 10,
            ],
            'low_og' => [
                'minimum' => 3,
                'deduction' => 10,
            ],
            'oversized_html' => [
                'threshold' => 900 * 1024,
                'deduction' => 20,
            ],
            'excess_images' => [
                'threshold' => 60,
                'deduction' => 10,
            ],
        ],
    ];

    private bool $enabled;

    /**
     * @var array<int, string>
     */
    private array $monitoredPaths;

    private array $penalties;

    public function __construct(?array $config = null)
    {
        $validated = $this->validateConfig($config ?? config('ssrmetrics', []));

        $this->enabled = $validated['enabled'];
        $this->monitoredPaths = $validated['paths'];
        $this->penalties = $validated['penalties'];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<int, string>
     */
    public function monitoredPaths(): array
    {
        return $this->monitoredPaths;
    }

    public function compute(string $path, string $content, string $contentType = 'text/html', int $firstByteMs = 0): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        $normalisedPath = $this->normalisePath($path);

        if ($this->monitoredPaths !== [] && ! in_array($normalisedPath, $this->monitoredPaths, true)) {
            return null;
        }

        if ($contentType === '' || stripos($contentType, 'text/html') === false) {
            return null;
        }

        $htmlBytes = strlen($content);
        $metaCount = preg_match_all('/<meta\b[^>]*>/i', $content);
        $ogCount = preg_match_all('/<meta\s+property=["\']og:/i', $content);
        $ldjsonCount = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $content);
        $imageCount = preg_match_all('/<img\b[^>]*>/i', $content);
        $blockingScripts = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $content);

        $score = 100;

        if ($blockingScripts > 0) {
            $perScriptPenalty = (int) $this->penalties['blocking_scripts']['per_script'];
            $maxPenalty = (int) $this->penalties['blocking_scripts']['max'];
            $score -= min($maxPenalty, $perScriptPenalty * $blockingScripts);
        }

        if ($ldjsonCount === 0) {
            $score -= (int) $this->penalties['missing_ldjson']['deduction'];
        }

        $minimumOgTags = (int) $this->penalties['low_og']['minimum'];

        if ($ogCount < $minimumOgTags) {
            $score -= (int) $this->penalties['low_og']['deduction'];
        }

        $oversizedThreshold = (int) $this->penalties['oversized_html']['threshold'];

        if ($htmlBytes > $oversizedThreshold) {
            $score -= (int) $this->penalties['oversized_html']['deduction'];
        }

        $excessImageThreshold = (int) $this->penalties['excess_images']['threshold'];

        if ($imageCount > $excessImageThreshold) {
            $score -= (int) $this->penalties['excess_images']['deduction'];
        }

        $score = max(0, $score);

        $meta = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $htmlBytes,
            'html_bytes' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imageCount,
            'blocking_scripts' => $blockingScripts,
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
        ];

        $payload = [
            'path' => $normalisedPath,
            'score' => $score,
            'collected_at' => Carbon::now()->toIso8601String(),
            'html_bytes' => $htmlBytes,
            'html_size' => $htmlBytes,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imageCount,
            'blocking_scripts' => $blockingScripts,
            'first_byte_ms' => $firstByteMs,
            'meta' => $meta,
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
        ];

        return [
            'score' => $score,
            'payload' => $payload,
        ];
    }

    private function validateConfig(?array $config): array
    {
        if (! is_array($config)) {
            $config = [];
        }

        $config = array_replace_recursive(self::DEFAULTS, $config);

        $config['enabled'] = (bool) ($config['enabled'] ?? false);

        if (! is_array($config['paths'])) {
            $config['paths'] = [];
        }

        if (! is_array($config['penalties'])) {
            $config['penalties'] = self::DEFAULTS['penalties'];
        } else {
            $config['penalties'] = array_replace_recursive(self::DEFAULTS['penalties'], $config['penalties']);
        }

        $config['paths'] = $this->normalisePaths($config['paths']);

        return $config;
    }

    private function normalisePath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return array<int, string>
     */
    private function normalisePaths(array $paths): array
    {
        $normalised = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);

            if ($path === '') {
                continue;
            }

            $normalised[] = $this->normalisePath($path);
        }

        return array_values(array_unique($normalised));
    }
}

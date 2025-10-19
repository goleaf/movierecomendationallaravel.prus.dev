<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

final class RecommendationWeightsSettings extends Settings
{
    private const VARIANT_KEYS = ['pop', 'recent', 'pref'];

    /** @var array<string, float> */
    public array $A = [
        'pop' => 0.55,
        'recent' => 0.20,
        'pref' => 0.25,
    ];

    /** @var array<string, float> */
    public array $B = [
        'pop' => 0.35,
        'recent' => 0.15,
        'pref' => 0.50,
    ];

    /** @var array<string, float> */
    public array $ab_split = [
        'A' => 50.0,
        'B' => 50.0,
    ];

    public ?string $seed = null;

    public static function group(): string
    {
        return 'recommendation-weights';
    }

    /**
     * @return array{
     *     A: array<string, float>,
     *     B: array<string, float>,
     *     ab_split: array<string, float>,
     *     seed: null|string
     * }
     */
    public static function defaults(): array
    {
        return [
            'A' => [
                'pop' => 0.55,
                'recent' => 0.20,
                'pref' => 0.25,
            ],
            'B' => [
                'pop' => 0.35,
                'recent' => 0.15,
                'pref' => 0.50,
            ],
            'ab_split' => [
                'A' => 50.0,
                'B' => 50.0,
            ],
            'seed' => null,
        ];
    }

    public static function fromRepository(SettingsRepository $repository): self
    {
        return new self(static::preparePayload(
            $repository->getPropertiesInGroup(static::group())
        ));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     A: array<string, float>,
     *     B: array<string, float>,
     *     ab_split: array<string, float>,
     *     seed: null|string
     * }
     */
    public static function preparePayload(array $overrides = []): array
    {
        $defaults = static::defaults();

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($defaults, $overrides);

        return [
            'A' => static::castVariantWeights($merged['A'] ?? [], $defaults['A']),
            'B' => static::castVariantWeights($merged['B'] ?? [], $defaults['B']),
            'ab_split' => [
                'A' => max(0.0, (float) ($merged['ab_split']['A'] ?? $defaults['ab_split']['A'])),
                'B' => max(0.0, (float) ($merged['ab_split']['B'] ?? $defaults['ab_split']['B'])),
            ],
            'seed' => isset($merged['seed']) && $merged['seed'] !== ''
                ? (string) $merged['seed']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *     A: array<string, float>,
     *     B: array<string, float>,
     *     ab_split: array<string, float>,
     *     seed: null|string
     * }
     */
    public static function store(SettingsRepository $repository, array $overrides = []): array
    {
        $current = $repository->getPropertiesInGroup(static::group());
        $payload = static::preparePayload(array_replace_recursive($current, $overrides));

        $group = static::group();

        $toUpdate = [];

        foreach ($payload as $property => $value) {
            if ($repository->checkIfPropertyExists($group, $property)) {
                $toUpdate[$property] = $value;

                continue;
            }

            $repository->createProperty($group, $property, $value);
        }

        if ($toUpdate !== []) {
            $repository->updatePropertiesPayload($group, $toUpdate);
        }

        return $payload;
    }

    public static function forget(SettingsRepository $repository, string ...$properties): void
    {
        $group = static::group();

        foreach ($properties as $property) {
            if ($repository->checkIfPropertyExists($group, $property)) {
                $repository->deleteProperty($group, $property);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $weights
     * @param  array<string, float>  $defaults
     * @return array<string, float>
     */
    private static function castVariantWeights(array $weights, array $defaults): array
    {
        $sanitised = [];

        foreach (self::VARIANT_KEYS as $key) {
            $value = $weights[$key] ?? $defaults[$key];
            $sanitised[$key] = max(0.0, (float) $value);
        }

        return $sanitised;
    }
}

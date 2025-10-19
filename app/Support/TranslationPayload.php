<?php

declare(strict_types=1);

namespace App\Support;

final class TranslationPayload
{
    /**
     * Normalize mixed translation payloads into a consistent structure.
     *
     * @param  array<string, mixed>|null  $translations
     * @return array{title: array<string, string>, plot: array<string, string>}
     */
    public static function normalize(?array $translations): array
    {
        $normalized = [
            'title' => [],
            'plot' => [],
        ];

        if (! is_array($translations)) {
            return $normalized;
        }

        if (array_key_exists('title', $translations) || array_key_exists('plot', $translations)) {
            foreach (['title', 'plot'] as $field) {
                $normalized[$field] = self::filterValues($translations[$field] ?? []);
            }

            return $normalized;
        }

        foreach ($translations as $locale => $payload) {
            if (! is_string($locale) || ! is_array($payload)) {
                continue;
            }

            foreach (['title', 'plot'] as $field) {
                $value = $payload[$field] ?? null;

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $normalized[$field][$locale] = $value;
            }
        }

        foreach (['title', 'plot'] as $field) {
            if ($normalized[$field] !== []) {
                ksort($normalized[$field]);
            }
        }

        return $normalized;
    }

    /**
     * Prepare a translation payload for persistence.
     *
     * @param  array<string, mixed>|null  $translations
     * @return array{title: array<string, string>, plot: array<string, string>}|null
     */
    public static function prepare(?array $translations): ?array
    {
        $normalized = self::normalize($translations);

        $hasValues = false;

        foreach ($normalized as $values) {
            if ($values !== []) {
                $hasValues = true;
                break;
            }
        }

        return $hasValues ? $normalized : null;
    }

    /**
     * Merge translation payloads while keeping the data normalized.
     *
     * @param  array<string, mixed>|null  $base
     * @param  array<string, mixed>|null  $updates
     * @return array{title: array<string, string>, plot: array<string, string>}|null
     */
    public static function merge(?array $base, ?array $updates): ?array
    {
        $normalizedBase = self::normalize($base);
        $normalizedUpdates = self::normalize($updates);

        foreach (['title', 'plot'] as $field) {
            foreach ($normalizedUpdates[$field] as $locale => $value) {
                $normalizedBase[$field][$locale] = $value;
            }

            if ($normalizedBase[$field] !== []) {
                ksort($normalizedBase[$field]);
            }
        }

        return self::prepare($normalizedBase);
    }

    /**
     * @return array<string, string>
     */
    private static function filterValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $filtered = [];

        foreach ($values as $locale => $value) {
            if (! is_string($locale) || ! is_string($value) || $value === '') {
                continue;
            }

            $filtered[$locale] = $value;
        }

        if ($filtered !== []) {
            ksort($filtered);
        }

        return $filtered;
    }
}

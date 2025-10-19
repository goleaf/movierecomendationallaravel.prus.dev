<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TranslationPayload
{
    /** @var array<string, string> */
    private const LANGUAGE_CODE_MAP = [
        'aar' => 'aa',
        'afr' => 'af',
        'ara' => 'ar',
        'bel' => 'be',
        'bul' => 'bg',
        'bos' => 'bs',
        'cat' => 'ca',
        'ces' => 'cs',
        'cze' => 'cs',
        'chi' => 'zh',
        'zho' => 'zh',
        'dan' => 'da',
        'deu' => 'de',
        'ger' => 'de',
        'ell' => 'el',
        'gre' => 'el',
        'eng' => 'en',
        'est' => 'et',
        'fin' => 'fi',
        'fra' => 'fr',
        'fre' => 'fr',
        'heb' => 'he',
        'hin' => 'hi',
        'hrv' => 'hr',
        'hun' => 'hu',
        'ind' => 'id',
        'isl' => 'is',
        'ita' => 'it',
        'jpn' => 'ja',
        'kor' => 'ko',
        'lav' => 'lv',
        'lit' => 'lt',
        'mac' => 'mk',
        'mkd' => 'mk',
        'may' => 'ms',
        'msa' => 'ms',
        'dut' => 'nl',
        'nld' => 'nl',
        'nor' => 'no',
        'pol' => 'pl',
        'por' => 'pt',
        'ron' => 'ro',
        'rum' => 'ro',
        'rus' => 'ru',
        'slo' => 'sk',
        'slk' => 'sk',
        'slv' => 'sl',
        'spa' => 'es',
        'srp' => 'sr',
        'swe' => 'sv',
        'tha' => 'th',
        'tur' => 'tr',
        'ukr' => 'uk',
        'vie' => 'vi',
    ];

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

            $normalizedLocale = self::normalizeLocaleKey($locale);

            if ($normalizedLocale === null) {
                continue;
            }

            foreach (['title', 'plot'] as $field) {
                $value = $payload[$field] ?? null;

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $normalized[$field][$normalizedLocale] = $value;
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

            $normalizedLocale = self::normalizeLocaleKey($locale);

            if ($normalizedLocale === null) {
                continue;
            }

            $filtered[$normalizedLocale] = $value;
        }

        if ($filtered !== []) {
            ksort($filtered);
        }

        return $filtered;
    }

    private static function normalizeLocaleKey(string $locale): ?string
    {
        $normalized = Str::of($locale)
            ->lower()
            ->trim()
            ->replace('_', '-')
            ->value();

        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, self::LANGUAGE_CODE_MAP)) {
            return self::LANGUAGE_CODE_MAP[$normalized];
        }

        if (str_contains($normalized, '-')) {
            $parts = array_values(array_filter(explode('-', $normalized), static fn (string $part): bool => $part !== ''));

            if ($parts === []) {
                return null;
            }

            $primary = self::LANGUAGE_CODE_MAP[$parts[0]] ?? $parts[0];
            $tail = array_slice($parts, 1);

            return $primary.(empty($tail) ? '' : '-'.implode('-', $tail));
        }

        return $normalized;
    }
}

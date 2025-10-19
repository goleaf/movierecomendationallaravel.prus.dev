<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class PaginatorQuery
{
    /**
     * @param  array<string, mixed>  $query
     */
    private function __construct(private array $query) {}

    public static function fromRequest(Request $request): self
    {
        return new self(self::normalize($request->query()));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->query;
    }

    public function applyTo(Request $request): void
    {
        $request->query->replace($this->query);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<int, mixed>
     */
    public function values(string $key): array
    {
        $value = $this->query[$key] ?? null;

        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function merge(array $overrides): array
    {
        return self::normalize(array_merge($this->query, $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        if ($keys === []) {
            return [];
        }

        $keyMap = array_flip($keys);

        return array_intersect_key($this->query, $keyMap);
    }

    /**
     * @return array<string, mixed>
     */
    public function except(string ...$keys): array
    {
        if ($keys === []) {
            return $this->query;
        }

        $keyMap = array_flip($keys);

        return array_diff_key($this->query, $keyMap);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function normalize(array $query): array
    {
        $normalized = [];

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $items = self::normalizeArray($value);

                if ($items === []) {
                    continue;
                }

                $normalized[$key] = $items;

                continue;
            }

            $item = self::normalizeValue($value);

            if ($item === null) {
                continue;
            }

            $normalized[$key] = $item;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private static function normalizeArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $item = self::normalizeValue($value);

            if ($item === null) {
                continue;
            }

            if (! in_array($item, $normalized, true)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return $value;
        }

        return null;
    }
}

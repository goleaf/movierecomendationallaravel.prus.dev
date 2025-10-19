<?php

declare(strict_types=1);

namespace App\Support;

use App\Filters\RangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class MovieSearchFilters
{
    public const ALLOWED_TYPES = ['movie', 'series', 'animation'];

    /**
     * @param  array{min:int|null,max:int|null}|null  $runtime
     * @param  array{min:float|null,max:float|null}|null  $rating
     */
    public function __construct(
        public readonly string $query,
        public readonly ?string $type,
        public readonly ?string $genre,
        public readonly ?int $yearFrom,
        public readonly ?int $yearTo,
        public readonly ?array $runtime = null,
        public readonly ?array $rating = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->query());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $query = trim((string) ($data['q'] ?? ''));
        $type = self::normalizeType($data['type'] ?? null);
        $genre = self::normalizeString($data['genre'] ?? null);
        $yearFrom = self::normalizeYear($data['yf'] ?? null);
        $yearTo = self::normalizeYear($data['yt'] ?? null);
        $runtime = self::normalizeRuntime($data['runtime'] ?? null);
        $rating = self::normalizeRating($data['rating'] ?? null);

        if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        return new self($query, $type, $genre, $yearFrom, $yearTo, $runtime, $rating);
    }

    public function apply(Builder $builder): Builder
    {
        if ($this->query !== '') {
            $search = $this->query;

            $builder->where(static function (Builder $where) use ($search): void {
                $where->where('title', 'like', "%{$search}%")
                    ->orWhere('imdb_tt', $search);
            });
        }

        if ($this->type !== null) {
            $builder->where('type', $this->type);
        }

        if ($this->genre !== null) {
            $builder->whereJsonContains('genres', $this->genre);
        }

        RangeFilter::for($builder)->forColumn('year', $this->yearFrom, $this->yearTo);

        if ($this->runtime !== null) {
            RangeFilter::for($builder)->forColumn('runtime_min', $this->runtime['min'] ?? null, $this->runtime['max'] ?? null);
        }

        if ($this->rating !== null) {
            RangeFilter::for($builder)->forColumn('imdb_rating', $this->rating['min'] ?? null, $this->rating['max'] ?? null);
        }

        return $builder;
    }

    public function toViewData(): array
    {
        return [
            'q' => $this->query,
            'type' => $this->type,
            'genre' => $this->genre,
            'yf' => $this->yearFrom,
            'yt' => $this->yearTo,
            'runtime' => $this->runtime,
            'rating' => $this->rating,
        ];
    }

    private static function normalizeType(mixed $value): ?string
    {
        $type = self::normalizeString($value);

        if ($type === null) {
            return null;
        }

        return in_array($type, self::ALLOWED_TYPES, true) ? $type : null;
    }

    private static function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) $value;

        if ($year < 1870 || $year > 2100) {
            return null;
        }

        return $year;
    }

    /**
     * @param  array{min:mixed,max:mixed}|mixed|null  $value
     * @return array{min:int|null,max:int|null}|null
     */
    private static function normalizeRuntime(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $min = array_key_exists('min', $value) ? self::normalizePositiveInt($value['min']) : null;
        $max = array_key_exists('max', $value) ? self::normalizePositiveInt($value['max']) : null;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    private static function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : null;
    }

    /**
     * @param  array{min:mixed,max:mixed}|mixed|null  $value
     * @return array{min:float|null,max:float|null}|null
     */
    private static function normalizeRating(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $min = array_key_exists('min', $value) ? self::normalizeRatingValue($value['min']) : null;
        $max = array_key_exists('max', $value) ? self::normalizeRatingValue($value['max']) : null;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return ['min' => $min, 'max' => $max];
    }

    private static function normalizeRatingValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $float = (float) $value;

        if ($float < 0.0 || $float > 10.0) {
            return null;
        }

        return round($float, 1);
    }
}

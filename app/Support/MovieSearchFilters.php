<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class MovieSearchFilters
{
    private const ALLOWED_TYPES = ['movie', 'series', 'animation'];

    public function __construct(
        public readonly string $query,
        public readonly ?string $type,
        public readonly ?string $genre,
        public readonly ?int $yearFrom,
        public readonly ?int $yearTo,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $query = self::normalizeString($request->query('q', '')) ?? '';
        $type = self::normalizeType($request->query('type'));
        $genre = self::normalizeString($request->query('genre'));
        $yearFrom = self::normalizeYear($request->query('yf'));
        $yearTo = self::normalizeYear($request->query('yt'));

        if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        return new self($query, $type, $genre, $yearFrom, $yearTo);
    }

    /**
     * @param  Builder<\App\Models\Movie>  $builder
     * @return Builder<\App\Models\Movie>
     */
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

        if ($this->yearFrom !== null) {
            $builder->where('year', '>=', $this->yearFrom);
        }

        if ($this->yearTo !== null) {
            $builder->where('year', '<=', $this->yearTo);
        }

        return $builder;
    }

    /**
     * @return array{q: string, type: ?string, genre: ?string, yf: ?int, yt: ?int}
     */
    public function toViewData(): array
    {
        return [
            'q' => $this->query,
            'type' => $this->type,
            'genre' => $this->genre,
            'yf' => $this->yearFrom,
            'yt' => $this->yearTo,
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

        if (is_array($value)) {
            $value = reset($value);
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
}

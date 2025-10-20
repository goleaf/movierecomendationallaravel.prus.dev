<?php

declare(strict_types=1);

namespace App\Search;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\SQLiteConnection;
use Normalizer;

final class DSL
{
    /**
     * @var array<int, true>
     */
    private static array $sqliteNormalizerRegistrations = [];

    private EloquentBuilder|BaseQueryBuilder $builder;

    public function __construct(EloquentBuilder|BaseQueryBuilder $builder)
    {
        QueryBuilderExtensions::register();

        $this->builder = $builder;
    }

    public static function for(EloquentBuilder|BaseQueryBuilder $builder): self
    {
        return new self($builder);
    }

    public function builder(): EloquentBuilder|BaseQueryBuilder
    {
        return $this->builder;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function search(array $columns, string $input): EloquentBuilder|BaseQueryBuilder
    {
        $tokens = $this->tokenize($input);

        if ($tokens === []) {
            return $this->builder;
        }

        $this->builder->whereAll(array_map(
            function (string $token) use ($columns): callable {
                return $this->whereAny($columns, $token);
            },
            $tokens
        ));

        return $this->builder;
    }

    public function like(string $column, string $value): callable
    {
        $pattern = $this->pattern($value);

        return $this->likeWithPattern($column, $pattern);
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function whereAny(array $columns, string $value): callable
    {
        $pattern = $this->pattern($value);

        return function (EloquentBuilder|BaseQueryBuilder $query) use ($columns, $pattern): void {
            $query->whereAny(array_map(
                fn (string $column): callable => $this->likeWithPattern($column, $pattern),
                $columns
            ));
        };
    }

    /**
     * @param  array<int, string>  $columns
     * @param  iterable<int, string>  $values
     */
    public function whereAll(array $columns, iterable $values): callable
    {
        $patterns = [];

        foreach ($values as $value) {
            $normalized = $this->normalizeToken($value);

            if ($normalized === '') {
                continue;
            }

            $patterns[] = $this->patternFromNormalized($normalized);
        }

        return function (EloquentBuilder|BaseQueryBuilder $query) use ($columns, $patterns): void {
            if ($patterns === []) {
                return;
            }

            $query->whereAll(array_map(
                function (string $pattern) use ($columns): callable {
                    return function (EloquentBuilder|BaseQueryBuilder $subQuery) use ($columns, $pattern): void {
                        $subQuery->whereAny(array_map(
                            fn (string $column): callable => $this->likeWithPattern($column, $pattern),
                            $columns
                        ));
                    };
                },
                $patterns
            ));
        };
    }

    private function likeWithPattern(string $column, string $pattern): callable
    {
        return function (EloquentBuilder|BaseQueryBuilder $query) use ($column, $pattern): void {
            $this->applyLike($query, $column, $pattern);
        };
    }

    private function applyLike(EloquentBuilder|BaseQueryBuilder $builder, string $column, string $pattern): void
    {
        $query = $builder instanceof EloquentBuilder ? $builder->getQuery() : $builder;
        $connection = $query->getConnection();

        if ($connection instanceof SQLiteConnection) {
            $this->registerSqliteNormalizer($connection);
            $wrapped = $query->getGrammar()->wrap($column);

            $builder->whereRaw('strip_diacritics('.$wrapped.') like ?', [$pattern]);

            return;
        }

        $builder->whereLike($column, $pattern);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];

        $normalized = array_map(
            fn (string $token): string => $this->normalizeToken($token),
            $tokens
        );

        $filtered = array_filter($normalized, static fn (string $token): bool => $token !== '');

        return array_values(array_unique($filtered));
    }

    private function pattern(string $value): string
    {
        $normalized = $this->normalizeToken($value);

        if ($normalized === '') {
            return '%%';
        }

        return $this->patternFromNormalized($normalized);
    }

    private function patternFromNormalized(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }

    private function normalizeToken(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return self::normalizeString($value);
    }

    private static function normalizeString(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);

            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        $value = preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');

        return strtr($value, self::extraNormalizations());
    }

    /**
     * @return array<string, string>
     */
    private static function extraNormalizations(): array
    {
        return [
            'ß' => 'ss',
            'æ' => 'ae',
            'œ' => 'oe',
            'ø' => 'o',
            'đ' => 'd',
            'ð' => 'd',
            'þ' => 'th',
            'ħ' => 'h',
            'ł' => 'l',
            'ŋ' => 'n',
            'ё' => 'е',
        ];
    }

    private function registerSqliteNormalizer(SQLiteConnection $connection): void
    {
        $pdo = $connection->getPdo();
        $identifier = spl_object_id($pdo);

        if (isset(self::$sqliteNormalizerRegistrations[$identifier])) {
            return;
        }

        $pdo->sqliteCreateFunction('strip_diacritics', static function ($value): string {
            if ($value === null) {
                return '';
            }

            return self::normalizeString((string) $value);
        });

        self::$sqliteNormalizerRegistrations[$identifier] = true;
    }
}

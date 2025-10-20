<?php

declare(strict_types=1);

namespace App\Search;

use Closure;
use InvalidArgumentException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Arr;

final class QueryBuilderExtensions
{
    /**
     * @var array<string, string>
     */
    private const CASE_FOLD_MAP = [
        'Ä' => 'ä',
        'Ö' => 'ö',
        'Ü' => 'ü',
        'ẞ' => 'ß',
        'Ë' => 'ë',
        'Ё' => 'ё',
        'А' => 'а',
        'Б' => 'б',
        'В' => 'в',
        'Г' => 'г',
        'Д' => 'д',
        'Е' => 'е',
        'Ж' => 'ж',
        'З' => 'з',
        'И' => 'и',
        'Й' => 'й',
        'К' => 'к',
        'Л' => 'л',
        'М' => 'м',
        'Н' => 'н',
        'О' => 'о',
        'П' => 'п',
        'Р' => 'р',
        'С' => 'с',
        'Т' => 'т',
        'У' => 'у',
        'Ф' => 'ф',
        'Х' => 'х',
        'Ц' => 'ц',
        'Ч' => 'ч',
        'Ш' => 'ш',
        'Щ' => 'щ',
        'Ъ' => 'ъ',
        'Ы' => 'ы',
        'Ь' => 'ь',
        'Э' => 'э',
        'Ю' => 'ю',
        'Я' => 'я',
    ];

    public static function register(): void
    {
        self::registerWhereLike();
        self::registerWhereAny();
        self::registerWhereAll();
        self::registerWhereNone();
    }

    private static function registerWhereLike(): void
    {
        $callback = function ($column, string $value, bool $caseSensitive = false, string $boolean = 'and', bool $not = false) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'whereLike')) {
                return $this->whereLike($column, $value, $caseSensitive, $boolean, $not);
            }

            return QueryBuilderExtensions::applyWhereLike($this, $column, $value, $caseSensitive, $boolean, $not);
        };

        self::macroOnBuilders('whereLike', $callback);

        $orCallback = function ($column, string $value, bool $caseSensitive = false) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'orWhereLike')) {
                return $this->orWhereLike($column, $value, $caseSensitive);
            }

            return QueryBuilderExtensions::applyWhereLike($this, $column, $value, $caseSensitive, 'or', false);
        };

        self::macroOnBuilders('orWhereLike', $orCallback);

        $notCallback = function ($column, string $value, bool $caseSensitive = false, string $boolean = 'and') {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'whereNotLike')) {
                return $this->whereNotLike($column, $value, $caseSensitive, $boolean);
            }

            return QueryBuilderExtensions::applyWhereLike($this, $column, $value, $caseSensitive, $boolean, true);
        };

        self::macroOnBuilders('whereNotLike', $notCallback);

        $orNotCallback = function ($column, string $value, bool $caseSensitive = false) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'orWhereNotLike')) {
                return $this->orWhereNotLike($column, $value, $caseSensitive);
            }

            return QueryBuilderExtensions::applyWhereLike($this, $column, $value, $caseSensitive, 'or', true);
        };

        self::macroOnBuilders('orWhereNotLike', $orNotCallback);
    }

    private static function registerWhereAny(): void
    {
        $callback = function ($columns, $operator = null, $value = null, string $boolean = 'and') {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'whereAny')) {
                return $this->whereAny($columns, $operator, $value, $boolean);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, $boolean, 'any', $twoArguments);
        };

        self::macroOnBuilders('whereAny', $callback);

        $orCallback = function ($columns, $operator = null, $value = null) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'orWhereAny')) {
                return $this->orWhereAny($columns, $operator, $value);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, 'or', 'any', $twoArguments);
        };

        self::macroOnBuilders('orWhereAny', $orCallback);
    }

    private static function registerWhereAll(): void
    {
        $callback = function ($columns, $operator = null, $value = null, string $boolean = 'and') {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'whereAll')) {
                return $this->whereAll($columns, $operator, $value, $boolean);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, $boolean, 'all', $twoArguments);
        };

        self::macroOnBuilders('whereAll', $callback);

        $orCallback = function ($columns, $operator = null, $value = null) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'orWhereAll')) {
                return $this->orWhereAll($columns, $operator, $value);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, 'or', 'all', $twoArguments);
        };

        self::macroOnBuilders('orWhereAll', $orCallback);
    }

    private static function registerWhereNone(): void
    {
        $callback = function ($columns, $operator = null, $value = null, string $boolean = 'and') {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'whereNone')) {
                return $this->whereNone($columns, $operator, $value, $boolean);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, $boolean, 'none', $twoArguments);
        };

        self::macroOnBuilders('whereNone', $callback);

        $orCallback = function ($columns, $operator = null, $value = null) {
            /** @var BaseQueryBuilder $this */
            if (method_exists(BaseQueryBuilder::class, 'orWhereNone')) {
                return $this->orWhereNone($columns, $operator, $value);
            }

            $twoArguments = func_num_args() === 2;

            return QueryBuilderExtensions::applyWhereColumns($this, (array) $columns, $operator, $value, 'or', 'none', $twoArguments);
        };

        self::macroOnBuilders('orWhereNone', $orCallback);
    }

    private static function macroOnBuilders(string $name, Closure $queryCallback): void
    {
        if (! method_exists(BaseQueryBuilder::class, $name) && ! BaseQueryBuilder::hasMacro($name)) {
            BaseQueryBuilder::macro($name, $queryCallback);
        }

        if (! method_exists(EloquentBuilder::class, $name) && ! EloquentBuilder::hasGlobalMacro($name)) {
            EloquentBuilder::macro($name, function (...$parameters) use ($name) {
                /** @var EloquentBuilder $this */
                $this->query->{$name}(...$parameters);

                return $this;
            });
        }
    }

    private static function applyWhereLike(BaseQueryBuilder $builder, $column, string $value, bool $caseSensitive, string $boolean, bool $not): BaseQueryBuilder
    {
        $columns = Arr::wrap($column);

        if (count($columns) > 1) {
            $method = $boolean === 'or' ? 'orWhere' : 'where';

            $builder->{$method}(function (BaseQueryBuilder $query) use ($columns, $value, $caseSensitive, $not): void {
                foreach ($columns as $index => $nestedColumn) {
                    if ($nestedColumn instanceof Closure) {
                        $query->{$index === 0 ? 'where' : 'orWhere'}($nestedColumn);

                        continue;
                    }

                    if (! is_string($nestedColumn)) {
                        continue;
                    }

                    $booleanForColumn = $index === 0 ? 'and' : 'or';

                    self::applyWhereLikeSingle($query, $nestedColumn, $value, $caseSensitive, $booleanForColumn, $not);
                }
            });

            return $builder;
        }

        $column = $columns[0] ?? null;

        if ($column instanceof Closure) {
            $method = $boolean === 'or' ? 'orWhere' : 'where';

            return $builder->{$method}(function (BaseQueryBuilder $query) use ($column, $value, $caseSensitive, $not): void {
                $column($query, $value, $caseSensitive, $not);
            });
        }

        if (! is_string($column)) {
            return $builder;
        }

        return self::applyWhereLikeSingle($builder, $column, $value, $caseSensitive, $boolean, $not);
    }

    private static function applyWhereLikeSingle(BaseQueryBuilder $builder, string $column, string $value, bool $caseSensitive, string $boolean, bool $not): BaseQueryBuilder
    {
        $connection = $builder->getConnection();
        $operator = $not ? 'not like' : 'like';
        $value = self::prepareLikeValue($builder, $value, $caseSensitive);

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if (! $caseSensitive && self::requiresLowercaseFallback($connection)) {
            $expression = self::buildCaseInsensitiveExpression($builder, $column);
            $sql = sprintf('%s %s ?', $expression, $not ? 'not like' : 'like');
            $binding = mb_strtolower($value, 'UTF-8');

            $rawMethod = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

            return $builder->{$rawMethod}($sql, [$binding]);
        }

        return $builder->{$method}($column, $operator, $value);
    }

    private static function applyRawLike(BaseQueryBuilder $builder, string $column, string $value, string $boolean, bool $not): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $operator = $not ? 'not like' : 'like';

        $builder->{$method}($column, $operator, $value);
    }

    private static function applyWhereColumns(BaseQueryBuilder $builder, array $columns, $operator, $value, string $boolean, string $mode, bool $twoArguments): BaseQueryBuilder
    {
        [$value, $operator] = $builder->prepareValueAndOperator($value, $operator, $twoArguments);

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if ($mode === 'none') {
            return self::applyWhereNoneColumns($builder, $columns, $operator, $value, $method);
        }

        $builder->{$method}(function (BaseQueryBuilder $query) use ($columns, $operator, $value, $mode): void {
            foreach (Arr::wrap($columns) as $index => $column) {
                $clauseMethod = $mode === 'any' && $index > 0 ? 'orWhere' : 'where';

                if ($column instanceof Closure) {
                    $query->{$clauseMethod}($column);

                    continue;
                }

                $query->{$clauseMethod}($column, $operator, $value);
            }
        });

        return $builder;
    }

    private static function applyWhereNoneColumns(BaseQueryBuilder $builder, array $columns, $operator, $value, string $method): BaseQueryBuilder
    {
        $builder->{$method}(function (BaseQueryBuilder $query) use ($columns, $operator, $value): void {
            foreach (Arr::wrap($columns) as $column) {
                if ($column instanceof Closure) {
                    throw new InvalidArgumentException('Closures are not supported for whereNone fallbacks.');
                }

                if (self::isLikeOperator($operator)) {
                    $query->whereNotLike($column, $value);

                    continue;
                }

                $query->where($column, self::negateOperator($operator), $value);
            }
        });

        return $builder;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $patternSets
     * @return array<int, Closure(BaseQueryBuilder|EloquentBuilder): void>
     */
    public static function whereAllLikeAcrossColumns(array $columns, array $patternSets, bool $caseSensitive = false): array
    {
        return array_map(
            static function (array $patterns) use ($columns, $caseSensitive): Closure {
                return static function (BaseQueryBuilder|EloquentBuilder $query) use ($columns, $patterns, $caseSensitive): void {
                    $query->whereAny(array_map(
                        static function (string $column) use ($patterns): Closure {
                            return static function (BaseQueryBuilder|EloquentBuilder $columnQuery) use ($column, $patterns): void {
                                if ($column === 'raw->cast') {
                                    QueryBuilderExtensions::addJsonArrayLikeCondition($columnQuery, $column, $patterns);

                                    return;
                                }

                                foreach ($patterns as $index => $pattern) {
                                    $boolean = $index === 0 ? 'and' : 'or';

                                    QueryBuilderExtensions::addLikeCondition($columnQuery, $column, $pattern, false, $boolean, false, true);
                                }
                            };
                        },
                        $columns
                    ));
                };
            },
            $patternSets
        );
    }

    public static function addLikeCondition(EloquentBuilder|BaseQueryBuilder $query, string $column, string $pattern, bool $caseSensitive = false, string $boolean = 'and', bool $not = false, bool $raw = false): void
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;

        if ($raw) {
            self::applyRawLike($base, $column, $pattern, $boolean, $not);

            return;
        }

        self::applyWhereLikeSingle($base, $column, $pattern, $caseSensitive, $boolean, $not);
    }

    public static function addJsonArrayLikeCondition(EloquentBuilder|BaseQueryBuilder $query, string $column, array $patterns, string $boolean = 'and'): void
    {
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        [$baseColumn, $jsonPath] = self::splitJsonColumn($column);

        if ($baseColumn === null || $jsonPath === null) {
            foreach ($patterns as $index => $pattern) {
                $clauseBoolean = $index === 0 ? $boolean : 'or';

                self::addLikeCondition($query, $column, $pattern, false, $clauseBoolean, false, true);
            }

            return;
        }

        $grammar = $base->getGrammar();
        $wrappedBase = $grammar->wrap($baseColumn);
        $jsonLiteral = self::quoteString($base, '$.'.str_replace('->', '.', $jsonPath));
        $conditions = implode(' or ', array_fill(0, count($patterns), 'json_each.value like ?'));
        $sql = sprintf('exists(select 1 from json_each(%s, %s) where %s)', $wrappedBase, $jsonLiteral, $conditions);
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

        $base->{$method}($sql, $patterns);
    }

    private static function prepareLikeValue(BaseQueryBuilder $builder, string $value, bool $caseSensitive): string
    {
        $grammar = $builder->getGrammar();

        if (method_exists($grammar, 'prepareWhereLikeBinding')) {
            return $grammar->prepareWhereLikeBinding($value, $caseSensitive);
        }

        return $caseSensitive ? $value : mb_strtolower($value, 'UTF-8');
    }

    private static function requiresLowercaseFallback(ConnectionInterface $connection): bool
    {
        return ! $connection->getQueryGrammar() instanceof PostgresGrammar;
    }

    private static function buildCaseInsensitiveExpression(BaseQueryBuilder $builder, string $column): string
    {
        $grammar = $builder->getGrammar();
        $expression = $grammar->wrap($column);

        foreach (self::CASE_FOLD_MAP as $upper => $lower) {
            $expression = sprintf('replace(%s, %s, %s)', $expression, self::quoteString($builder, $upper), self::quoteString($builder, $lower));
        }

        return sprintf('lower(%s)', $expression);
    }

    private static function quoteString(BaseQueryBuilder $builder, string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function splitJsonColumn(string $column): array
    {
        if (! str_contains($column, '->')) {
            return [null, null];
        }

        $parts = explode('->', $column, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }

    private static function isLikeOperator($operator): bool
    {
        if (! is_string($operator)) {
            return false;
        }

        return in_array(strtolower($operator), ['like', 'not like', 'ilike', 'not ilike'], true);
    }

    private static function negateOperator($operator): string
    {
        $normalized = is_string($operator) ? strtolower($operator) : '=';

        return match ($normalized) {
            '=', '==' => '!=',
            '!=' => '=',
            '<>' => '=',
            '>' => '<=',
            '>=' => '<',
            '<' => '>=',
            '<=' => '>',
            'not like' => 'like',
            'like', 'ilike', 'not ilike' => 'not like',
            default => '!=',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Search;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Arr;

final class QueryBuilderExtensions
{
    public static function register(): void
    {
        self::registerWhereLike();
        self::registerWhereAny();
        self::registerWhereAll();
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
        $connection = $builder->getConnection();
        $operator = $not ? 'not like' : 'like';
        $value = self::prepareLikeValue($builder, $value, $caseSensitive);

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if (! $caseSensitive && self::requiresLowercaseFallback($connection)) {
            $grammar = $builder->getGrammar();
            $wrapped = $grammar->wrap($column);
            $sql = sprintf('lower(%s) %s ?', $wrapped, $not ? 'not like' : 'like');
            $binding = mb_strtolower($value, 'UTF-8');

            $rawMethod = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';

            return $builder->{$rawMethod}($sql, [$binding]);
        }

        return $builder->{$method}($column, $operator, $value);
    }

    private static function applyWhereColumns(BaseQueryBuilder $builder, array $columns, $operator, $value, string $boolean, string $mode, bool $twoArguments): BaseQueryBuilder
    {
        [$value, $operator] = $builder->prepareValueAndOperator($value, $operator, $twoArguments);

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $builder->{$method}(function (BaseQueryBuilder $query) use ($columns, $operator, $value, $mode): void {
            foreach (Arr::wrap($columns) as $index => $column) {
                if ($column instanceof Closure) {
                    $subMethod = $mode === 'any' && $index > 0 ? 'orWhere' : 'where';

                    $query->{$subMethod}($column);

                    continue;
                }

                if ($mode === 'any') {
                    if ($index === 0) {
                        $query->where($column, $operator, $value);
                    } else {
                        $query->orWhere($column, $operator, $value);
                    }
                } else {
                    $query->where($column, $operator, $value);
                }
            }
        });

        return $builder;
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
}

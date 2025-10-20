<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Closure;
use DateTimeInterface;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class MemoPolicy
{
    public const FILTERS_TTL_SECONDS = 30;

    public const GENRES_TTL_SECONDS = 30;

    private const FILTERS_KEY_PREFIX = 'filters.config';

    private const GENRES_KEY_PREFIX = 'genres.list';

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function rememberFilters(string $segment, array $context, Closure $resolver, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(
            'hot_path_filters',
            self::FILTERS_KEY_PREFIX,
            $segment,
            $context,
            $ttlSeconds ?? self::FILTERS_TTL_SECONDS,
            $resolver,
        );
    }

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function rememberGenres(string $segment, array $context, Closure $resolver, ?int $ttlSeconds = null): mixed
    {
        return $this->remember(
            'hot_path_genres',
            self::GENRES_KEY_PREFIX,
            $segment,
            $context,
            $ttlSeconds ?? self::GENRES_TTL_SECONDS,
            $resolver,
        );
    }

    public function filtersKey(string $segment, array $context): string
    {
        return $this->buildKey(self::FILTERS_KEY_PREFIX, $segment, $context);
    }

    public function genresKey(string $segment, array $context): string
    {
        return $this->buildKey(self::GENRES_KEY_PREFIX, $segment, $context);
    }

    public function filtersTtl(): int
    {
        return self::FILTERS_TTL_SECONDS;
    }

    public function genresTtl(): int
    {
        return self::GENRES_TTL_SECONDS;
    }

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    private function remember(
        string $storeName,
        string $prefix,
        string $segment,
        array $context,
        int $ttlSeconds,
        Closure $resolver,
    ): mixed {
        $repository = $this->repository($storeName);
        $key = $this->buildKey($prefix, $segment, $context);
        $expiresAt = now()->addSeconds($ttlSeconds);

        $computed = false;
        $startedAt = microtime(true);

        Log::debug('memo.policy.before', [
            'store' => $storeName,
            'key' => $key,
            'ttl_seconds' => $ttlSeconds,
        ]);

        $value = $repository->remember($key, $expiresAt, function () use ($resolver, &$computed) {
            $computed = true;

            return $resolver();
        });

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::debug('memo.policy.after', [
            'store' => $storeName,
            'key' => $key,
            'hit' => ! $computed,
            'duration_ms' => $durationMs,
        ]);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildKey(string $prefix, string $segment, array $context): string
    {
        $normalized = $this->normalize($context);
        $hash = md5((string) json_encode($normalized, JSON_THROW_ON_ERROR));

        return implode('.', [$prefix, $segment, $hash]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalize(array $context): array
    {
        ksort($context);

        foreach ($context as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $context[$key] = $value->format(DateTimeInterface::ATOM);

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $context[$key] = $this->normalize($nested);

                continue;
            }

            if (is_object($value)) {
                /** @var array<string, mixed> $cast */
                $cast = (array) $value;
                $context[$key] = $this->normalize($cast);

                continue;
            }

            $context[$key] = $value;
        }

        return $context;
    }

    private function repository(string $storeName): Repository
    {
        $config = config("cache.stores.{$storeName}");

        if (is_array($config) && ($config['driver'] ?? null) === 'memoized') {
            $underlying = is_string($config['store'] ?? null) ? $config['store'] : null;

            if (is_string($underlying) && $underlying !== '') {
                return Cache::memo($underlying);
            }
        }

        return Cache::store($storeName);
    }
}

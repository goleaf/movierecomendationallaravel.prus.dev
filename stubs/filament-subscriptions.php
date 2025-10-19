<?php

declare(strict_types=1);

namespace TomatoPHP\FilamentSubscriptions\Facades;

use TomatoPHP\FilamentSubscriptions\Services\Contracts\SubscriberContract;

/**
 * @method static FilamentSubscriptionCollection<int, SubscriberContract> getOptions()
 * @method static void register(SubscriberContract $subscriber)
 */
final class FilamentSubscriptions
{
}

/**
 * @template TKey of array-key
 * @template TValue
 */
interface FilamentSubscriptionCollection
{
    /**
     * @param callable(TValue): bool $callback
     */
    public function doesntContain(callable $callback): bool;
}

namespace TomatoPHP\FilamentSubscriptions\Services\Contracts;

/**
 * @property-read class-string|null $model
 */
interface SubscriberContract
{
    /**
     * @return class-string|null
     */
    public function getModel(): ?string;
}

final class Subscriber implements SubscriberContract
{
    /**
     * @var class-string|null
     */
    public ?string $model = null;

    public static function make(string $name): SubscriberContract
    {
        return new self();
    }

    public function model(string $model): SubscriberContract
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }
}

<?php

declare(strict_types=1);

namespace Laravelcm\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 */
class Plan extends Model
{
    public const INTERVAL_DAY = 'day';

    public const INTERVAL_WEEK = 'week';

    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';
}

/**
 * @property int $id
 */
class Feature extends Model
{
}

/**
 * @property int $id
 */
class Subscription extends Model
{
}

/**
 * @property int $id
 */
class SubscriptionUsage extends Model
{
}

namespace {

    use Laravelcm\Subscriptions\Models\Plan;

    function plan(string $name): Plan
    {
        return new Plan();
    }
}

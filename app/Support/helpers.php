<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravelcm\Subscriptions\Models\Feature;
use Laravelcm\Subscriptions\Models\Plan;
use Laravelcm\Subscriptions\Models\Subscription;
use Laravelcm\Subscriptions\Models\SubscriptionUsage;

if (! function_exists('device_id')) {
    function device_id(): string
    {
        $key = 'did';
        $deviceId = request()->cookie($key);

        if ($deviceId === null || $deviceId === '') {
            $deviceId = 'd_'.Str::uuid()->toString();
            Cookie::queue(Cookie::make($key, $deviceId, 60 * 24 * 365 * 5));
        }

        return $deviceId;
    }
}

if (! function_exists('plan')) {
    /**
     * @return Builder<Plan>
     */
    function plan(): Builder
    {
        /** @var Builder<Plan> $query */
        $query = Plan::query();

        return $query;
    }
}

if (! function_exists('plan_feature')) {
    /**
     * @return Builder<Feature>
     */
    function plan_feature(): Builder
    {
        /** @var Builder<Feature> $query */
        $query = Feature::query();

        return $query;
    }
}

if (! function_exists('plan_subscription')) {
    /**
     * @return Builder<Subscription>
     */
    function plan_subscription(): Builder
    {
        /** @var Builder<Subscription> $query */
        $query = Subscription::query();

        return $query;
    }
}

if (! function_exists('plan_subscription_usage')) {
    /**
     * @return Builder<SubscriptionUsage>
     */
    function plan_subscription_usage(): Builder
    {
        /** @var Builder<SubscriptionUsage> $query */
        $query = SubscriptionUsage::query();

        return $query;
    }
}

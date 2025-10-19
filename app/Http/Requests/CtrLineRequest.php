<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;

class CtrLineRequest extends DateRangeRequest
{
    protected function defaultFrom(): CarbonImmutable
    {
        return now()->subDays(14)->toImmutable();
    }

    protected function defaultTo(): CarbonImmutable
    {
        return now()->toImmutable();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use App\Http\Requests\CtrRangeRequest;
use App\Http\Requests\TrendsRequest;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

final class AnalyticsRequestSanitizationTest extends TestCase
{
    public function test_ctr_range_request_sanitizes_invalid_inputs(): void
    {
        Carbon::setTestNow('2025-01-10 09:00:00');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-10 09:00:00'));

        $base = Request::create('/admin/analytics/ctr', 'GET', [
            'from' => 'bad-date',
            'to' => '2025-13-99',
            'p' => 'unknown',
            'v' => 'Z',
        ]);

        /** @var CtrRangeRequest $request */
        $request = CtrRangeRequest::createFromBase($base);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $range = $request->range();

        $this->assertSame('2025-01-03', $range['from']->format('Y-m-d'));
        $this->assertSame('2025-01-10', $range['to']->format('Y-m-d'));
        $this->assertNull($request->placement());
        $this->assertNull($request->variant());

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    public function test_trends_request_swaps_year_range_and_clamps_days(): void
    {
        $base = Request::create('/admin/analytics/trends', 'GET', [
            'days' => 99,
            'type' => '  movie ',
            'genre' => ' drama ',
            'yf' => '2024',
            'yt' => '2019',
        ]);

        /** @var TrendsRequest $request */
        $request = TrendsRequest::createFromBase($base);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $filters = $request->filters();

        $this->assertSame(30, $filters['days']);
        $this->assertSame('movie', $filters['type']);
        $this->assertSame('drama', $filters['genre']);
        $this->assertSame(2019, $filters['yf']);
        $this->assertSame(2024, $filters['yt']);
        $this->assertSame('movie', $request->type());
        $this->assertSame('drama', $request->genre());
        $this->assertSame(2019, $request->yearFrom());
        $this->assertSame(2024, $request->yearTo());
    }
}

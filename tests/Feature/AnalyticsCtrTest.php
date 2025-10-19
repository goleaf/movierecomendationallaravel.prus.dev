<?php

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Http\Controllers\CtrController;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsCtrTest extends TestCase
{
    use RefreshDatabase;

    public function test_ctr_controller_uses_placement_impressions_for_funnels(): void
    {
        Carbon::setTestNow('2025-01-10 00:00:00');

        $movieId = DB::table('movies')->insertGetId([
            'imdb_tt' => 'tt0000001',
            'title' => 'Test Movie',
            'type' => 'movie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $impressionTime = Carbon::parse('2025-01-08 12:00:00');

        DB::table('rec_ab_logs')->insert(array_merge(
            $this->makeImpressions('home', 'A', 2, $movieId, $impressionTime),
            $this->makeImpressions('home', 'B', 1, $movieId, $impressionTime),
            $this->makeImpressions('show', 'A', 3, $movieId, $impressionTime),
            $this->makeImpressions('show', 'B', 2, $movieId, $impressionTime),
            $this->makeImpressions('trends', 'A', 1, $movieId, $impressionTime),
            $this->makeImpressions('trends', 'B', 1, $movieId, $impressionTime),
        ));

        DB::table('rec_clicks')->insert(array_merge(
            $this->makeClicks('home', 'A', 1, $movieId, $impressionTime),
            $this->makeClicks('show', 'A', 1, $movieId, $impressionTime),
            $this->makeClicks('show', 'B', 1, $movieId, $impressionTime),
            $this->makeClicks('trends', 'B', 1, $movieId, $impressionTime),
        ));

        DB::table('device_history')->insert([
            'device_id' => 'device-views',
            'page' => 'home',
            'movie_id' => $movieId,
            'viewed_at' => $impressionTime,
            'created_at' => $impressionTime,
            'updated_at' => $impressionTime,
        ]);

        $request = Request::create('/admin/ctr', 'GET', [
            'from' => '2025-01-05',
            'to' => '2025-01-10',
        ]);

        $view = app(CtrController::class)->index($request);

        $funnels = $view->getData()['funnels'];

        $this->assertSame(3, $funnels['home']['imps']);
        $this->assertSame(1, $funnels['home']['clks']);
        $this->assertSame(5, $funnels['show']['imps']);
        $this->assertSame(2, $funnels['show']['clks']);
        $this->assertSame(2, $funnels['trends']['imps']);
        $this->assertSame(1, $funnels['trends']['clks']);
        $this->assertSame(10, $funnels['Итого']['imps']);
        $this->assertSame(4, $funnels['Итого']['clks']);
    }

    public function test_funnel_widget_rows_use_placement_impressions(): void
    {
        Carbon::setTestNow('2025-01-10 00:00:00');

        $movieId = DB::table('movies')->insertGetId([
            'imdb_tt' => 'tt0000002',
            'title' => 'Widget Movie',
            'type' => 'movie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $impressionTime = Carbon::parse('2025-01-08 12:00:00');

        DB::table('rec_ab_logs')->insert(array_merge(
            $this->makeImpressions('home', 'A', 2, $movieId, $impressionTime),
            $this->makeImpressions('home', 'B', 1, $movieId, $impressionTime),
            $this->makeImpressions('show', 'A', 3, $movieId, $impressionTime),
            $this->makeImpressions('show', 'B', 2, $movieId, $impressionTime),
            $this->makeImpressions('trends', 'A', 1, $movieId, $impressionTime),
            $this->makeImpressions('trends', 'B', 1, $movieId, $impressionTime),
        ));

        DB::table('rec_clicks')->insert(array_merge(
            $this->makeClicks('home', 'A', 1, $movieId, $impressionTime),
            $this->makeClicks('show', 'A', 1, $movieId, $impressionTime),
            $this->makeClicks('show', 'B', 1, $movieId, $impressionTime),
            $this->makeClicks('trends', 'B', 1, $movieId, $impressionTime),
        ));

        DB::table('device_history')->insert([
            'device_id' => 'device-views',
            'page' => 'home',
            'movie_id' => $movieId,
            'viewed_at' => $impressionTime,
            'created_at' => $impressionTime,
            'updated_at' => $impressionTime,
        ]);

        $widget = new class extends FunnelWidget
        {
            public function exposedGetViewData(): array
            {
                return $this->getViewData();
            }
        };

        $rows = $widget->exposedGetViewData()['rows'];

        $home = $this->findRow($rows, 'home');
        $show = $this->findRow($rows, 'show');
        $trends = $this->findRow($rows, 'trends');
        $total = $this->findRow($rows, 'Итого');

        $this->assertSame(3, $home['imps']);
        $this->assertSame(1, $home['clicks']);
        $this->assertEqualsWithDelta(33.33, $home['ctr'], 0.01);

        $this->assertSame(5, $show['imps']);
        $this->assertSame(2, $show['clicks']);
        $this->assertEqualsWithDelta(40.0, $show['ctr'], 0.01);

        $this->assertSame(2, $trends['imps']);
        $this->assertSame(1, $trends['clicks']);
        $this->assertEqualsWithDelta(50.0, $trends['ctr'], 0.01);

        $this->assertSame(10, $total['imps']);
        $this->assertSame(4, $total['clicks']);
        $this->assertEqualsWithDelta(40.0, $total['ctr'], 0.01);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeImpressions(string $placement, string $variant, int $count, int $movieId, Carbon $timestamp): array
    {
        return array_map(function (int $index) use ($placement, $variant, $movieId, $timestamp) {
            return [
                'device_id' => 'device-'.$placement.'-'.$variant.'-'.$index,
                'placement' => $placement,
                'variant' => $variant,
                'movie_id' => $movieId,
                'payload' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, range(1, $count));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function makeClicks(string $placement, string $variant, int $count, int $movieId, Carbon $timestamp): array
    {
        return array_map(function (int $index) use ($placement, $variant, $movieId, $timestamp) {
            return [
                'movie_id' => $movieId,
                'device_id' => 'click-'.$placement.'-'.$variant.'-'.$index,
                'placement' => $placement,
                'variant' => $variant,
                'clicked_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, range(1, $count));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function findRow(array $rows, string $label): array
    {
        foreach ($rows as $row) {
            if ($row['label'] === $label) {
                return $row;
            }
        }

        $this->fail('Expected to find row with label '.$label);
    }
}

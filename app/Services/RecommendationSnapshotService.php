<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RecommendationSnapshotService
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    /**
     * @param  Collection<int, \App\Models\Movie>  $movies
     */
    public function record(string $variant, Collection $movies): void
    {
        if (! Schema::hasTable('rec_variant_snapshots')) {
            return;
        }

        $itemCount = 0;
        $popTotal = 0.0;
        $recentTotal = 0.0;
        $prefTotal = 0.0;
        $scoreTotal = 0.0;
        $weights = null;

        foreach ($movies as $movie) {
            $meta = $movie->getAttribute('rec_variant_scores');
            if (! is_array($meta)) {
                continue;
            }

            $itemCount++;
            $popTotal += (float) ($meta['pop'] ?? 0.0);
            $recentTotal += (float) ($meta['recent'] ?? 0.0);
            $prefTotal += (float) ($meta['pref'] ?? 0.0);
            $scoreTotal += (float) ($meta['total'] ?? 0.0);

            if ($weights === null) {
                $weights = $movie->getAttribute('rec_variant_weights');
            }
        }

        if ($itemCount === 0) {
            return;
        }

        $capturedOn = now()->toDateString();
        $now = now();
        $weightPop = (float) ($weights['pop'] ?? 0.0);
        $weightRecent = (float) ($weights['recent'] ?? 0.0);
        $weightPref = (float) ($weights['pref'] ?? 0.0);

        $updated = $this->db->table('rec_variant_snapshots')
            ->where('variant', $variant)
            ->where('captured_on', $capturedOn)
            ->increment('item_count', $itemCount, [
                'pop_total' => $this->expression('pop_total', $popTotal),
                'recent_total' => $this->expression('recent_total', $recentTotal),
                'pref_total' => $this->expression('pref_total', $prefTotal),
                'score_total' => $this->expression('score_total', $scoreTotal),
                'weight_pop_total' => $this->expression('weight_pop_total', $weightPop * $itemCount),
                'weight_recent_total' => $this->expression('weight_recent_total', $weightRecent * $itemCount),
                'weight_pref_total' => $this->expression('weight_pref_total', $weightPref * $itemCount),
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            $this->db->table('rec_variant_snapshots')->insert([
                'variant' => $variant,
                'captured_on' => $capturedOn,
                'item_count' => $itemCount,
                'pop_total' => $popTotal,
                'recent_total' => $recentTotal,
                'pref_total' => $prefTotal,
                'score_total' => $scoreTotal,
                'weight_pop_total' => $weightPop * $itemCount,
                'weight_recent_total' => $weightRecent * $itemCount,
                'weight_pref_total' => $weightPref * $itemCount,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array{
     *     days: array<int, string>,
     *     series: array<string, array<int, float>>,
     *     weights: array<string, array<int, float>>,
     *     rows: array<int, array<string, mixed>>,
     *     max: float
     * }
     */
    public function dailySeries(string $variant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable('rec_variant_snapshots')) {
            return [
                'days' => [],
                'series' => ['pop' => [], 'recent' => [], 'pref' => []],
                'weights' => ['pop' => [], 'recent' => [], 'pref' => []],
                'rows' => [],
                'max' => 0.0,
            ];
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $rows = $this->db->table('rec_variant_snapshots')
            ->select([
                'captured_on',
                'item_count',
                'pop_total',
                'recent_total',
                'pref_total',
                'score_total',
                'weight_pop_total',
                'weight_recent_total',
                'weight_pref_total',
            ])
            ->where('variant', $variant)
            ->whereBetween('captured_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('captured_on')
            ->get()
            ->keyBy(static fn ($row) => (string) $row->captured_on);

        $days = [];
        $current = $from->startOfDay();
        $end = $to->startOfDay();
        while ($current->lessThanOrEqualTo($end)) {
            $days[] = $current->toDateString();
            $current = $current->addDay();
        }

        $series = [
            'pop' => [],
            'recent' => [],
            'pref' => [],
        ];
        $weightSeries = [
            'pop' => [],
            'recent' => [],
            'pref' => [],
        ];
        $tableRows = [];
        $max = 0.0;

        foreach ($days as $day) {
            $row = $rows->get($day);
            if ($row !== null) {
                $count = max(1, (int) $row->item_count);
                $popAvg = (float) $row->pop_total / $count;
                $recentAvg = (float) $row->recent_total / $count;
                $prefAvg = (float) $row->pref_total / $count;
                $scoreAvg = (float) $row->score_total / $count;
                $weightPopAvg = (float) $row->weight_pop_total / $count;
                $weightRecentAvg = (float) $row->weight_recent_total / $count;
                $weightPrefAvg = (float) $row->weight_pref_total / $count;

                $series['pop'][] = round(max(0.0, $popAvg), 4);
                $series['recent'][] = round(max(0.0, $recentAvg), 4);
                $series['pref'][] = round(max(0.0, $prefAvg), 4);

                $weightSeries['pop'][] = round(max(0.0, min(1.0, $weightPopAvg)), 4);
                $weightSeries['recent'][] = round(max(0.0, min(1.0, $weightRecentAvg)), 4);
                $weightSeries['pref'][] = round(max(0.0, min(1.0, $weightPrefAvg)), 4);

                $max = max($max, $popAvg, $recentAvg, $prefAvg);

                $tableRows[] = [
                    'day' => $day,
                    'items' => (int) $row->item_count,
                    'pop' => round($popAvg, 4),
                    'recent' => round($recentAvg, 4),
                    'pref' => round($prefAvg, 4),
                    'score' => round($scoreAvg, 4),
                    'weights' => [
                        'pop' => round($weightPopAvg, 4),
                        'recent' => round($weightRecentAvg, 4),
                        'pref' => round($weightPrefAvg, 4),
                    ],
                ];
            } else {
                $series['pop'][] = 0.0;
                $series['recent'][] = 0.0;
                $series['pref'][] = 0.0;
                $weightSeries['pop'][] = 0.0;
                $weightSeries['recent'][] = 0.0;
                $weightSeries['pref'][] = 0.0;

                $tableRows[] = [
                    'day' => $day,
                    'items' => 0,
                    'pop' => 0.0,
                    'recent' => 0.0,
                    'pref' => 0.0,
                    'score' => 0.0,
                    'weights' => [
                        'pop' => 0.0,
                        'recent' => 0.0,
                        'pref' => 0.0,
                    ],
                ];
            }
        }

        return [
            'days' => $days,
            'series' => $series,
            'weights' => $weightSeries,
            'rows' => $tableRows,
            'max' => $max,
        ];
    }

    public function buildContributionSvg(array $data): ?string
    {
        $days = $data['days'] ?? [];
        if ($days === []) {
            return null;
        }

        $series = $data['series'] ?? [];
        $max = (float) ($data['max'] ?? 0.0);
        $max = max(0.1, ceil(max($max, 0.0) * 20) / 20);

        $width = 720;
        $height = 260;
        $pad = 40;

        $mapSeries = static function (array $values) use ($max, $width, $height, $pad): string {
            $values = array_values($values);
            $count = count($values);
            if ($count === 0) {
                return '';
            }

            $horizontalRange = max(1.0, $width - (2 * $pad));
            $verticalRange = max(1.0, $height - (2 * $pad));
            $steps = max(1, $count - 1);
            $points = [];

            foreach ($values as $index => $value) {
                $x = $pad + ($index * $horizontalRange / $steps);
                $normalized = $max > 0.0 ? max(0.0, (float) $value) / $max : 0.0;
                $y = $height - $pad - ($normalized * $verticalRange);
                $points[] = sprintf('%.1f,%.1f', $x, $y);
            }

            return implode(' ', $points);
        };

        $colors = [
            'pop' => '#60a5fa',
            'recent' => '#facc15',
            'pref' => '#f472b6',
        ];

        $grid = '';
        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + ($i * ($height - (2 * $pad)) / 5);
            $value = round($max - ($i * $max / 5), 2);
            $grid .= '<line x1="'.$pad.'" y1="'.$y.'" x2="'.($width - $pad).'" y2="'.$y.'" stroke="#1d2229" stroke-width="1" />';
            $grid .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$value.'</text>';
        }

        $legendY = 24;
        $legendX = $pad;
        $legendSpacing = 120;
        $legend = '';
        $labels = [
            'pop' => __('admin.experiments.snapshots.labels.pop'),
            'recent' => __('admin.experiments.snapshots.labels.recent'),
            'pref' => __('admin.experiments.snapshots.labels.pref'),
        ];
        $index = 0;
        foreach ($labels as $key => $label) {
            $legend .= '<rect x="'.($legendX + ($index * $legendSpacing)).'" y="'.$legendY.'" width="12" height="12" rx="2" fill="'.$colors[$key].'" />';
            $legend .= '<text x="'.($legendX + ($index * $legendSpacing) + 18).'" y="'.($legendY + 10).'" fill="#ddd" font-size="11">'.$label.'</text>';
            $index++;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'">'
            .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#0b0c0f" />'
            .$grid
            .$legend
            .'<polyline fill="none" stroke="'.$colors['pop'].'" stroke-width="2" points="'.$mapSeries($series['pop'] ?? []).'" />'
            .'<polyline fill="none" stroke="'.$colors['recent'].'" stroke-width="2" points="'.$mapSeries($series['recent'] ?? []).'" />'
            .'<polyline fill="none" stroke="'.$colors['pref'].'" stroke-width="2" points="'.$mapSeries($series['pref'] ?? []).'" />'
            .'<text x="10" y="16" fill="#ddd">'.e(__('admin.experiments.snapshots.contribution_chart_title')).'</text>'
            .'</svg>';
    }

    public function buildWeightSvg(array $data): ?string
    {
        $days = $data['days'] ?? [];
        if ($days === []) {
            return null;
        }

        $series = $data['weights'] ?? [];
        $width = 720;
        $height = 260;
        $pad = 40;

        $mapSeries = static function (array $values) use ($width, $height, $pad): string {
            $values = array_values($values);
            $count = count($values);
            if ($count === 0) {
                return '';
            }

            $horizontalRange = max(1.0, $width - (2 * $pad));
            $verticalRange = max(1.0, $height - (2 * $pad));
            $steps = max(1, $count - 1);
            $points = [];

            foreach ($values as $index => $value) {
                $x = $pad + ($index * $horizontalRange / $steps);
                $normalized = max(0.0, min(1.0, (float) $value));
                $y = $height - $pad - ($normalized * $verticalRange);
                $points[] = sprintf('%.1f,%.1f', $x, $y);
            }

            return implode(' ', $points);
        };

        $colors = [
            'pop' => '#38bdf8',
            'recent' => '#fbbf24',
            'pref' => '#c084fc',
        ];

        $grid = '';
        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + ($i * ($height - (2 * $pad)) / 5);
            $value = round(1 - ($i / 5), 2);
            $grid .= '<line x1="'.$pad.'" y1="'.$y.'" x2="'.($width - $pad).'" y2="'.$y.'" stroke="#1d2229" stroke-width="1" />';
            $grid .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$value.'</text>';
        }

        $legendY = 24;
        $legendX = $pad;
        $legendSpacing = 120;
        $legend = '';
        $labels = [
            'pop' => __('admin.experiments.snapshots.labels.pop_weight'),
            'recent' => __('admin.experiments.snapshots.labels.recent_weight'),
            'pref' => __('admin.experiments.snapshots.labels.pref_weight'),
        ];
        $index = 0;
        foreach ($labels as $key => $label) {
            $legend .= '<rect x="'.($legendX + ($index * $legendSpacing)).'" y="'.$legendY.'" width="12" height="12" rx="2" fill="'.$colors[$key].'" />';
            $legend .= '<text x="'.($legendX + ($index * $legendSpacing) + 18).'" y="'.($legendY + 10).'" fill="#ddd" font-size="11">'.$label.'</text>';
            $index++;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'">'
            .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#0b0c0f" />'
            .$grid
            .$legend
            .'<polyline fill="none" stroke="'.$colors['pop'].'" stroke-width="2" points="'.$mapSeries($series['pop'] ?? []).'" />'
            .'<polyline fill="none" stroke="'.$colors['recent'].'" stroke-width="2" points="'.$mapSeries($series['recent'] ?? []).'" />'
            .'<polyline fill="none" stroke="'.$colors['pref'].'" stroke-width="2" points="'.$mapSeries($series['pref'] ?? []).'" />'
            .'<text x="10" y="16" fill="#ddd">'.e(__('admin.experiments.snapshots.weight_chart_title')).'</text>'
            .'</svg>';
    }

    private function expression(string $column, float $value): Expression
    {
        return DB::raw($column.' + '.number_format($value, 6, '.', ''));
    }
}

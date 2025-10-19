<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsController
{
    public function __invoke(): Response
    {
        $lines = [];

        $lines[] = '# HELP movierec_ctr_per_placement Click-through rate (ratio) by placement.';
        $lines[] = '# TYPE movierec_ctr_per_placement gauge';
        foreach ($this->ctrPerPlacement() as $placement => $ctr) {
            $lines[] = sprintf(
                'movierec_ctr_per_placement{placement="%s"} %.6f',
                $this->escapeLabel($placement),
                $ctr
            );
        }

        $lines[] = '# HELP movierec_ssr_ttfb_p95ms 95th percentile of SSR time to first byte in milliseconds.';
        $lines[] = '# TYPE movierec_ssr_ttfb_p95ms gauge';
        $lines[] = sprintf('movierec_ssr_ttfb_p95ms %.2f', $this->ssrTtfbP95());

        $lines[] = '# HELP movierec_importer_errors_total Total number of importer failures recorded in the system.';
        $lines[] = '# TYPE movierec_importer_errors_total counter';
        $lines[] = sprintf('movierec_importer_errors_total %d', $this->importerErrorsCount());

        $body = implode("\n", $lines)."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    /**
     * @return array<string, float>
     */
    private function ctrPerPlacement(): array
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('rec_clicks')) {
            return [];
        }

        $impressions = DB::table('rec_ab_logs')
            ->selectRaw("COALESCE(NULLIF(TRIM(placement), ''), 'unknown') as placement, count(*) as total")
            ->groupBy('placement')
            ->pluck('total', 'placement');

        $clicks = DB::table('rec_clicks')
            ->selectRaw("COALESCE(NULLIF(TRIM(placement), ''), 'unknown') as placement, count(*) as total")
            ->groupBy('placement')
            ->pluck('total', 'placement');

        /** @var Collection<int, string> $placements */
        $placements = $impressions->keys()->merge($clicks->keys())->unique()->sort();

        $values = [];
        foreach ($placements as $placement) {
            $imp = (int) ($impressions[$placement] ?? 0);
            $clk = (int) ($clicks[$placement] ?? 0);

            if ($imp <= 0) {
                $values[$placement] = 0.0;

                continue;
            }

            $values[$placement] = round($clk / $imp, 6);
        }

        return $values;
    }

    private function ssrTtfbP95(): float
    {
        $values = collect($this->ssrTtfbFromDatabase());

        if ($values->isEmpty()) {
            $values = collect($this->ssrTtfbFromFilesystem());
        }

        $filtered = $values
            ->filter(static fn ($value) => is_numeric($value) && (float) $value >= 0.0)
            ->map(static fn ($value): float => (float) $value)
            ->sort()
            ->values();

        if ($filtered->isEmpty()) {
            return 0.0;
        }

        $index = (int) floor(0.95 * (max(1, $filtered->count()) - 1));
        $index = max(0, min($index, $filtered->count() - 1));

        return round($filtered->get($index) ?? 0.0, 2);
    }

    private function importerErrorsCount(): int
    {
        $count = 0;

        if (Schema::hasTable('failed_jobs')) {
            $count += $this->importerErrorsFromFailedJobs();
        }

        $count += $this->importerErrorsFromLogs();

        return $count;
    }

    /**
     * @return array<int, float>
     */
    private function ssrTtfbFromDatabase(): array
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return [];
        }

        if (Schema::hasColumn('ssr_metrics', 'ttfb_ms')) {
            return DB::table('ssr_metrics')
                ->whereNotNull('ttfb_ms')
                ->pluck('ttfb_ms')
                ->map(static fn ($value) => is_numeric($value) ? (float) $value : null)
                ->filter()
                ->values()
                ->all();
        }

        if (! Schema::hasColumn('ssr_metrics', 'meta')) {
            return [];
        }

        return DB::table('ssr_metrics')
            ->whereNotNull('meta')
            ->pluck('meta')
            ->map(function ($meta) {
                if (is_array($meta)) {
                    $data = $meta;
                } elseif (is_object($meta)) {
                    $data = (array) $meta;
                } elseif (is_string($meta)) {
                    try {
                        /** @var mixed $decoded */
                        $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        return null;
                    }

                    $data = is_array($decoded) ? $decoded : [];
                } else {
                    $data = [];
                }

                $value = data_get($data, 'ttfb_ms') ?? data_get($data, 'ttfb') ?? data_get($data, 'timings.ttfb');

                return is_numeric($value) ? (float) $value : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function ssrTtfbFromFilesystem(): array
    {
        $path = storage_path('app/metrics/ssr.jsonl');
        if (! is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $values = [];

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                /** @var mixed $decoded */
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                continue;
            }

            if (! is_array($decoded)) {
                continue;
            }

            $value = data_get($decoded, 'ttfb_ms') ?? data_get($decoded, 'ttfb') ?? data_get($decoded, 'timings.ttfb');
            if (is_numeric($value)) {
                $values[] = (float) $value;
            }
        }

        fclose($handle);

        return $values;
    }

    private function importerErrorsFromFailedJobs(): int
    {
        $rows = DB::table('failed_jobs')->select('payload', 'queue')->get();

        $count = 0;
        foreach ($rows as $row) {
            $queue = strtolower((string) ($row->queue ?? ''));
            if ($queue !== '' && str_contains($queue, 'import')) {
                $count++;

                continue;
            }

            $payload = $this->decodePayload($row->payload);

            $displayName = strtolower((string) ($payload['displayName'] ?? ''));
            if ($displayName !== '' && str_contains($displayName, 'import')) {
                $count++;

                continue;
            }

            $commandName = strtolower((string) (data_get($payload, 'data.commandName') ?? ''));
            if ($commandName !== '' && str_contains($commandName, 'import')) {
                $count++;
            }
        }

        return $count;
    }

    private function importerErrorsFromLogs(): int
    {
        $paths = [
            storage_path('logs/importers.log'),
            storage_path('logs/importer.log'),
        ];

        $count = 0;

        foreach ($paths as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $handle = fopen($path, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (stripos($line, 'error') !== false) {
                    $count++;
                }
            }

            fclose($handle);
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', ' ', ' '], $value);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SsrMetricsService
{
    /**
     * @param  array{path: string, score: int, html_bytes: int|null, meta_count: int|null, og_count: int|null, ldjson_count: int|null, img_count: int|null, blocking_scripts: int|null, first_byte_ms: int, has_json_ld: bool, has_open_graph: bool, meta: array<string, mixed>, collected_at: \Illuminate\Support\Carbon}  $payload
     */
    public function store(array $payload): void
    {
        if ($this->storeInDatabase($payload)) {
            return;
        }

        $this->storeInJsonl($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInDatabase(array $payload): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $data = [
                'path' => $payload['path'],
                'score' => $payload['score'],
                'created_at' => $payload['collected_at']->toDateTimeString(),
                'updated_at' => $payload['collected_at']->toDateTimeString(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $data['first_byte_ms'] = $payload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $data['collected_at'] = $payload['collected_at']->toDateTimeString();
            }

            if (Schema::hasColumn('ssr_metrics', 'size') && $payload['html_bytes'] !== null) {
                $data['size'] = $payload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'html_bytes') && $payload['html_bytes'] !== null) {
                $data['html_bytes'] = $payload['html_bytes'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count') && $payload['meta_count'] !== null) {
                $data['meta_count'] = $payload['meta_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count') && $payload['og_count'] !== null) {
                $data['og_count'] = $payload['og_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count') && $payload['ldjson_count'] !== null) {
                $data['ldjson_count'] = $payload['ldjson_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count') && $payload['img_count'] !== null) {
                $data['img_count'] = $payload['img_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts') && $payload['blocking_scripts'] !== null) {
                $data['blocking_scripts'] = $payload['blocking_scripts'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $data['has_json_ld'] = $payload['has_json_ld'];
            }

            if (Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $data['has_open_graph'] = $payload['has_open_graph'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta')) {
                $data['meta'] = json_encode($payload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInJsonl(array $payload): void
    {
        try {
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $jsonPayload = [
                'ts' => $payload['collected_at']->toIso8601String(),
                'path' => $payload['path'],
                'score' => $payload['score'],
                'html_bytes' => $payload['html_bytes'],
                'size' => $payload['html_bytes'],
                'html_size' => $payload['html_bytes'],
                'meta' => $payload['meta'],
                'meta_count' => $payload['meta_count'],
                'og_count' => $payload['og_count'],
                'og' => $payload['og_count'],
                'ldjson_count' => $payload['ldjson_count'],
                'ld' => $payload['ldjson_count'],
                'img_count' => $payload['img_count'],
                'imgs' => $payload['img_count'],
                'blocking_scripts' => $payload['blocking_scripts'],
                'blocking' => $payload['blocking_scripts'],
                'first_byte_ms' => $payload['first_byte_ms'],
                'has_json_ld' => $payload['has_json_ld'],
                'has_open_graph' => $payload['has_open_graph'],
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($jsonPayload, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            Log::error('Failed storing SSR metric in JSONL.', [
                'exception' => $e,
                'payload' => $payload,
            ]);
        }
    }
}

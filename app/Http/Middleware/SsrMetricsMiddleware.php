<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! config('ssrmetrics.enabled')) {
            return $response;
        }

        $path = '/'.ltrim($request->path(), '/');

        if (! collect(config('ssrmetrics.paths', []))->contains($path)) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($contentType === '' || ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $firstByteMs = (int) round((microtime(true) - $startedAt) * 1000);

        $html = $response->getContent() ?? '';
        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $metaPayload = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'has_json_ld' => $ld > 0,
            'has_open_graph' => $og > 0,
        ];

        $score = 100;

        if ($blocking > 0) {
            $score -= min(30, 5 * $blocking);
        }

        if ($ld === 0) {
            $score -= 10;
        }

        if ($og < 3) {
            $score -= 10;
        }

        if ($size > 900 * 1024) {
            $score -= 20;
        }

        if ($imgs > 60) {
            $score -= 10;
        }

        $score = max(0, $score);

        if (Schema::hasTable('ssr_metrics')) {
            try {
                $data = [
                    'path' => $path,
                    'score' => $score,
                    'created_at' => now(),
                ];

                if (Schema::hasColumn('ssr_metrics', 'size')) {
                    $data['size'] = $size;
                }

                if (Schema::hasColumn('ssr_metrics', 'meta_count')) {
                    $data['meta_count'] = $meta;
                }

                if (Schema::hasColumn('ssr_metrics', 'og_count')) {
                    $data['og_count'] = $og;
                }

                if (Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                    $data['ldjson_count'] = $ld;
                }

                if (Schema::hasColumn('ssr_metrics', 'img_count')) {
                    $data['img_count'] = $imgs;
                }

                if (Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                    $data['blocking_scripts'] = $blocking;
                }

                if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                    $data['first_byte_ms'] = $firstByteMs;
                }

                if (Schema::hasColumn('ssr_metrics', 'meta')) {
                    $data['meta'] = json_encode($metaPayload, JSON_THROW_ON_ERROR);
                }

                DB::table('ssr_metrics')->insert($data);
            } catch (\Throwable $e) {
            }
        } else {
            try {
                Storage::append('metrics/ssr.jsonl', json_encode([
                    'ts' => now()->toIso8601String(),
                    'path' => $path,
                    'score' => $score,
                    'size' => $size,
                    'html_size' => $size,
                    'meta' => $meta,
                    'og' => $og,
                    'ld' => $ld,
                    'imgs' => $imgs,
                    'blocking' => $blocking,
                    'first_byte_ms' => $firstByteMs,
                    'has_json_ld' => $ld > 0,
                    'has_open_graph' => $og > 0,
                ]));
            } catch (\Throwable $e) {
            }
        }

        return $response;
    }
}

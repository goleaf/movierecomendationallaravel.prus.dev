<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
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

        $html = $response->getContent() ?? '';
        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

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

        if (\Schema::hasTable('ssr_metrics')) {
            try {
                DB::table('ssr_metrics')->insert([
                    'path' => $path,
                    'score' => $score,
                    'size' => $size,
                    'meta_count' => $meta,
                    'og_count' => $og,
                    'ldjson_count' => $ld,
                    'img_count' => $imgs,
                    'blocking_scripts' => $blocking,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }
        } else {
            try {
                Storage::append('metrics/ssr.jsonl', json_encode([
                    'ts' => now()->toIso8601String(),
                    'path' => $path,
                    'score' => $score,
                    'size' => $size,
                    'meta' => $meta,
                    'og' => $og,
                    'ld' => $ld,
                    'imgs' => $imgs,
                    'blocking' => $blocking,
                ]));
            } catch (\Throwable $e) {
            }
        }

        return $response;
    }
}

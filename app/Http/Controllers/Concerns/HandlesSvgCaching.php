<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\ParameterBag;

trait HandlesSvgCaching
{
    /**
     * @param array<string, mixed> $parameterOverrides
     * @param array<int, string> $tables
     */
    protected function cachedSvgResponse(Request $request, string $svg, array $parameterOverrides, array $tables): Response
    {
        $response = new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);

        $response->headers->set('Cache-Control', 'max-age=60, public');

        $etag = $this->makeEtag($request, $parameterOverrides, $tables);
        $response->setEtag($etag);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $parameterOverrides
     * @param array<int, string> $tables
     */
    private function makeEtag(Request $request, array $parameterOverrides, array $tables): string
    {
        $parameters = $request->query();

        if ($parameters instanceof ParameterBag) {
            $parameters = $parameters->all();
        }

        foreach ($parameterOverrides as $key => $value) {
            $parameters[$key] = $value;
        }

        ksort($parameters);

        $lastUpdatedAt = $this->resolveLastUpdatedAt($tables);

        $payload = [
            'parameters' => $parameters,
            'last_updated_at' => $lastUpdatedAt?->toIso8601String(),
        ];

        return sha1(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int, string> $tables
     */
    private function resolveLastUpdatedAt(array $tables): ?CarbonImmutable
    {
        $latest = null;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $value = DB::table($table)->max('updated_at');

            if ($value === null) {
                continue;
            }

            $timestamp = CarbonImmutable::parse($value);

            if ($latest === null || $timestamp->greaterThan($latest)) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }
}

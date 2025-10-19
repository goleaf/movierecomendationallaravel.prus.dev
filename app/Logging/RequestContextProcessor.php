<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Http\Request;

class RequestContextProcessor
{
    /**
     * @param  array<string,mixed>  $record
     * @return array<string,mixed>
     */
    public function __invoke(array $record): array
    {
        $request = request();
        if (! $request instanceof Request) {
            return $record;
        }

        $requestId = $request->attributes->get('request_id', $request->headers->get('X-Request-ID'));
        if (is_string($requestId) && $requestId !== '') {
            $record['context']['request_id'] = $requestId;
        }

        $deviceId = $request->attributes->get('device_id');
        if (is_string($deviceId) && $deviceId !== '') {
            $record['context']['device_id'] = $deviceId;
        }

        $variant = $request->attributes->get('ab_variant', $request->cookie('ab_variant'));
        if (is_string($variant) && $variant !== '') {
            $record['context']['ab_variant'] = $variant;
        }

        $filmId = $request->attributes->get('film_id', $request->headers->get('X-Film-ID', $request->query('film_id')));
        if (is_string($filmId) && $filmId !== '') {
            $record['context']['film_id'] = $filmId;
        }

        return $record;
    }
}

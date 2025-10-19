<?php

declare(strict_types=1);

return [
    'disk' => env('IMAGE_PROXY_DISK', 'local'),
    'directory' => env('IMAGE_PROXY_DIRECTORY', 'image-proxy'),
    'ttl' => (int) env('IMAGE_PROXY_TTL', 86400),
    'queue' => env('IMAGE_PROXY_QUEUE'),
    'headers' => [
        'Cache-Control' => env('IMAGE_PROXY_CACHE_CONTROL', 'public, max-age=604800, stale-while-revalidate=604800'),
    ],
];

<?php

declare(strict_types=1);

return [
    'enabled' => env('SSR_METRICS', false),
    'paths' => ['/', '/trends', '/analytics/ctr'],
];

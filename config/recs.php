<?php

declare(strict_types=1);

return [
    'A' => [
        'pop' => (float) env('REC_A_POP', 0.55),
        'recent' => (float) env('REC_A_RECENT', 0.20),
        'pref' => (float) env('REC_A_PREF', 0.25),
    ],
    'B' => [
        'pop' => (float) env('REC_B_POP', 0.35),
        'recent' => (float) env('REC_B_RECENT', 0.15),
        'pref' => (float) env('REC_B_PREF', 0.50),
    ],
];

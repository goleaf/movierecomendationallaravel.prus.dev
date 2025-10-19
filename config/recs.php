<?php

return [
    'A' => [
        'pop' => (float) env('RECS_A_POP', 0.5),
        'recent' => (float) env('RECS_A_RECENT', 0.2),
        'pref' => (float) env('RECS_A_PREF', 0.3),
    ],
    'B' => [
        'pop' => (float) env('RECS_B_POP', 0.4),
        'recent' => (float) env('RECS_B_RECENT', 0.4),
        'pref' => (float) env('RECS_B_PREF', 0.2),
    ],
];

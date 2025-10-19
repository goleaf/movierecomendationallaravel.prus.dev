<?php

return [
    'A' => [
        'pop' => (float) env('REC_A_POP', 0.7),
        'recent' => (float) env('REC_A_RECENT', 0.2),
        'pref' => (float) env('REC_A_PREF', 0.1),
    ],
    'B' => [
        'pop' => (float) env('REC_B_POP', 0.3),
        'recent' => (float) env('REC_B_RECENT', 0.6),
        'pref' => (float) env('REC_B_PREF', 0.1),
    ],
];

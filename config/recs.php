<?php

return [
    'A' => [
        'pop' => floatval(env('REC_A_POP', 0.7)),
        'recent' => floatval(env('REC_A_RECENT', 0.2)),
        'pref' => floatval(env('REC_A_PREF', 0.1)),
    ],
    'B' => [
        'pop' => floatval(env('REC_B_POP', 0.3)),
        'recent' => floatval(env('REC_B_RECENT', 0.6)),
        'pref' => floatval(env('REC_B_PREF', 0.1)),
    ],
];

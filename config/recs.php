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
    'ab_split' => (static function (): array {
        $raw = (string) env('REC_AB_SPLIT', '50,50');
        $parts = array_map(static fn (string $value): string => trim($value), explode(',', $raw));

        $weightA = isset($parts[0]) && is_numeric($parts[0]) ? (float) $parts[0] : 50.0;
        $weightB = isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 50.0;

        return [
            'A' => $weightA,
            'B' => $weightB,
        ];
    })(),
];

<?php

declare(strict_types=1);

use App\Support\Security\AppCspPreset;
use Spatie\Csp\Directive;

return [
    'presets' => [
        AppCspPreset::class,
    ],

    'directives' => [
        // [Directive::SCRIPT, ['https://example.com']],
    ],

    'report_only_presets' => [
        //
    ],

    'report_only_directives' => [
        // [Directive::STYLE, ['https://example.com']],
    ],

    'report_uri' => env('CSP_REPORT_URI'),

    'enabled' => env('CSP_ENABLED', true),

    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    'nonce_generator' => Spatie\Csp\Nonce\RandomString::class,

    'nonce_enabled' => env('CSP_NONCE_ENABLED', true),
];

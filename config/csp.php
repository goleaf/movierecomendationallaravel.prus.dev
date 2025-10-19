<?php

declare(strict_types=1);

use App\Support\ContentSecurityPolicyPreset;
use Spatie\Csp\Nonce\RandomString;

return [
    'presets' => [
        ContentSecurityPolicyPreset::class,
    ],

    'directives' => [
        //
    ],

    'report_only_presets' => [
        //
    ],

    'report_only_directives' => [
        //
    ],

    'report_uri' => env('CSP_REPORT_URI', ''),

    'enabled' => env('CSP_ENABLED', true),

    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    'nonce_generator' => RandomString::class,

    'nonce_enabled' => env('CSP_NONCE_ENABLED', false),
];

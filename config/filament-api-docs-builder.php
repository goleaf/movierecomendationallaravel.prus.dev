<?php

use InfinityXTech\FilamentApiDocsBuilder\Enums\PredefinedCodeBuilders;
use InfinityXTech\FilamentApiDocsBuilder\Filament\Resources\ApiDocsResource;
use InfinityXTech\FilamentApiDocsBuilder\Models\ApiDocs;

$resourceClass = class_exists(ApiDocsResource::class) ? ApiDocsResource::class : null;
$modelClass = class_exists(ApiDocs::class) ? ApiDocs::class : null;
$predefinedCodes = class_exists(PredefinedCodeBuilders::class)
    ? [PredefinedCodeBuilders::cURL]
    : [];

return [
    'code_builders' => [],
    'predefined_params' => [
        [
            'location' => 'header',
            'type' => 'string',
            'name' => 'Authorization',
            'value' => 'Bearer $TOKEN',
            'description' => '',
            'required' => true,
        ],
        [
            'location' => 'header',
            'type' => 'string',
            'name' => 'Content-Type',
            'value' => 'application/json',
            'description' => '',
            'required' => true,
        ],
        [
            'location' => 'header',
            'type' => 'string',
            'name' => 'Accept',
            'value' => 'application/json',
            'description' => '',
            'required' => true,
        ],
    ],
    'resource' => $resourceClass,
    'model' => $modelClass,
    'importer' => [
        'predefined_codes' => $predefinedCodes,
    ],
    'save_current_user' => false,
    'tenant' => null,
];

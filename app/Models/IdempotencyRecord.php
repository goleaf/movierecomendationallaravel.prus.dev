<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'date_key',
        'last_etag',
        'last_modified_at',
        'payload',
    ];

    protected $casts = [
        'date_key' => 'date',
        'last_modified_at' => 'datetime',
        'payload' => 'array',
    ];
}

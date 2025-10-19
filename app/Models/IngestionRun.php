<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property string $external_id
 * @property \Carbon\CarbonImmutable $date_key
 * @property string|null $last_etag
 * @property \Carbon\CarbonImmutable|null $last_modified_at
 * @property array|null $meta
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class IngestionRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_key' => 'immutable_date',
            'last_modified_at' => 'immutable_datetime',
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

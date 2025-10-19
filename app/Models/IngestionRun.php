<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sources
 * @property string $external_id
 * @property \Carbon\CarbonImmutable $ingested_on
 * @property string|null $last_etag
 * @property \Carbon\CarbonImmutable|null $last_modified
 */
class IngestionRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ingested_on' => 'immutable_date',
            'last_modified' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForKey(Builder $query, string $sources, string $externalId): Builder
    {
        return $query
            ->where('sources', $sources)
            ->where('external_id', $externalId);
    }
}

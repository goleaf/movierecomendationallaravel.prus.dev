<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $source
 * @property string $external_id
 * @property CarbonInterface $date_key
 * @property array|null $request_headers
 * @property array|null $request_payload
 * @property array|null $response_headers
 * @property array|null $response_payload
 * @property string|null $last_etag
 * @property CarbonInterface|null $last_modified_at
 */
class IngestionRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'ingestion_runs';

    protected function casts(): array
    {
        return [
            'date_key' => 'immutable_date',
            'request_headers' => 'array',
            'request_payload' => 'array',
            'response_headers' => 'array',
            'response_payload' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'last_modified_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where($query->qualifyColumn('source'), $source);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForExternalId(Builder $query, string $externalId): Builder
    {
        return $query->where($query->qualifyColumn('external_id'), $externalId);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForDateKey(Builder $query, CarbonInterface|string $dateKey): Builder
    {
        $value = $dateKey instanceof CarbonInterface
            ? $dateKey->toDateString()
            : $dateKey;

        return $query->whereDate($query->qualifyColumn('date_key'), $value);
    }
}

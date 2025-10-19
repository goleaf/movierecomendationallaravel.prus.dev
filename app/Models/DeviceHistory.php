<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceHistoryFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $device_id
 * @property string|null $placement
 * @property int|null $movie_id
 * @property \Carbon\CarbonImmutable $viewed_at
 * @property-read \Carbon\CarbonImmutable $created_at
 * @property-read \Carbon\CarbonImmutable $updated_at
 * @property-read Movie|null $movie
 *
 * @method static DeviceHistoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|self betweenViewedAt(DateTimeInterface|string $from, DateTimeInterface|string $to)
 * @method static Builder<static>|self forPlacement(?string $placement)
 */
class DeviceHistory extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceHistoryFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Movie, DeviceHistory>
     */
    public function movie(): BelongsTo
    {
        /** @var BelongsTo<Movie, DeviceHistory> $relation */
        $relation = $this->belongsTo(Movie::class);

        return $relation;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBetweenViewedAt(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): Builder
    {
        $column = $query->qualifyColumn('viewed_at');

        $query->whereBetween($column, [
            $from instanceof DateTimeInterface ? $from->format('Y-m-d H:i:s') : $from,
            $to instanceof DateTimeInterface ? $to->format('Y-m-d H:i:s') : $to,
        ]);

        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPlacement(Builder $query, ?string $placement): Builder
    {
        if ($placement === null || $placement === '') {
            return $query;
        }

        $query->where('placement', $placement);

        return $query;
    }

    protected static function newFactory(): DeviceHistoryFactory
    {
        return DeviceHistoryFactory::new();
    }
}

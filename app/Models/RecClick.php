<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $movie_id
 * @property string $device_id
 * @property string $placement
 * @property string $variant
 * @property string|null $source
 * @property-read \Carbon\CarbonImmutable $created_at
 * @property-read \Carbon\CarbonImmutable $updated_at
 * @property-read Movie|null $movie
 */
class RecClick extends Model
{
    /** @use HasFactory<\Database\Factories\RecClickFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Movie, RecClick>
     */
    public function movie(): BelongsTo
    {
        /** @var BelongsTo<Movie, RecClick> $relation */
        $relation = $this->belongsTo(Movie::class);

        return $relation;
    }

    /**
     * @param  Builder<RecClick>  $query
     * @return Builder<RecClick>
     */
    public function scopeBetweenCreatedAt(Builder $query, DateTimeInterface|string $from, DateTimeInterface|string $to): Builder
    {
        $column = $query->qualifyColumn('created_at');

        $query->whereBetween($column, [
            $from instanceof DateTimeInterface ? $from->format('Y-m-d H:i:s') : $from,
            $to instanceof DateTimeInterface ? $to->format('Y-m-d H:i:s') : $to,
        ]);

        return $query;
    }

    /**
     * @param  Builder<RecClick>  $query
     * @return Builder<RecClick>
     */
    public function scopeForVariant(Builder $query, ?string $variant): Builder
    {
        if ($variant === null || $variant === '') {
            return $query;
        }

        $query->where('variant', $variant);

        return $query;
    }

    /**
     * @param  Builder<RecClick>  $query
     * @return Builder<RecClick>
     */
    public function scopeForPlacement(Builder $query, ?string $placement): Builder
    {
        if ($placement === null || $placement === '') {
            return $query;
        }

        $query->where('placement', $placement);

        return $query;
    }
}

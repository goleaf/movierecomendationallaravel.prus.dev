<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $device_id
 * @property string $placement
 * @property string $variant
 * @property int|null $movie_id
 * @property array<string, mixed>|null $meta
 * @property-read \Carbon\CarbonImmutable $created_at
 * @property-read \Carbon\CarbonImmutable $updated_at
 * @property-read Movie|null $movie
 *
 * @method static Builder<static>|self betweenCreatedAt(DateTimeInterface|string $from, DateTimeInterface|string $to)
 * @method static Builder<static>|self forPlacement(?string $placement)
 * @method static Builder<static>|self forVariant(?string $variant)
 */
class RecAbLog extends Model
{
    /** @use HasFactory<\Database\Factories\RecAbLogFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Movie, static>
     */
    public function movie(): BelongsTo
    {
        /** @var BelongsTo<Movie, static> $relation */
        $relation = $this->belongsTo(Movie::class);

        return $relation;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForVariant(Builder $query, ?string $variant): Builder
    {
        if ($variant === null || $variant === '') {
            return $query;
        }

        return $query->where('variant', $variant);
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

        return $query->where('placement', $placement);
    }
}

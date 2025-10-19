<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\Commentions\Contracts\Commentable;
use Kirschbaum\Commentions\HasComments;

/**
 * @property int $id
 * @property string $imdb_tt
 * @property string $title
 * @property string|null $plot
 * @property string $type
 * @property int|null $year
 * @property \Carbon\CarbonImmutable|null $release_date
 * @property float|null $imdb_rating
 * @property int|null $imdb_votes
 * @property int|null $runtime_min
 * @property array<int,string>|null $genres
 * @property string|null $poster_url
 * @property string|null $backdrop_url
 * @property array{title?: array<string, string>, plot?: array<string, string>}|null $translations
 * @property array|null $raw
 * @property-read float $weighted_score
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecAbLog> $recAbLogs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecClick> $recClicks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeviceHistory> $deviceHistory
 */
class Movie extends Model implements Commentable
{
    use HasComments;
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['weighted_score'];

    protected function casts(): array
    {
        return [
            'release_date' => 'immutable_datetime:Y-m-d',
            'imdb_rating' => 'float',
            'imdb_votes' => 'integer',
            'runtime_min' => 'integer',
            'genres' => 'array',
            'translations' => 'array',
            'raw' => 'array',
        ];
    }

    public function getWeightedScoreAttribute(): float
    {
        $averageRating = (float) ($this->imdb_rating ?? 0.0);
        $voteCount = (int) ($this->imdb_votes ?? 0);
        $minimumVotes = 1000;
        $globalAverage = 6.8;

        if ($voteCount <= 0) {
            return 0.0;
        }

        $weighted = (($voteCount / ($voteCount + $minimumVotes)) * $averageRating)
            + (($minimumVotes / ($voteCount + $minimumVotes)) * $globalAverage);

        return round($weighted, 4);
    }

    public function recAbLogs(): HasMany
    {
        return $this->hasMany(RecAbLog::class);
    }

    public function recClicks(): HasMany
    {
        return $this->hasMany(RecClick::class);
    }

    public function deviceHistory(): HasMany
    {
        return $this->hasMany(DeviceHistory::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Traversable;
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
 *
 * @method static MovieFactory factory($count = null, $state = [])
 */
class Movie extends Model implements Commentable
{
    use HasComments;
    use HasFactory;

    /**
     * Map of normalized external genre labels to local canonical genres.
     *
     * @var array<string, array<int, string>>
     */
    private const GENRE_SYNONYMS = [
        'sci fi' => ['science fiction'],
        'scifi' => ['science fiction'],
        'sciencefiction' => ['science fiction'],
        'science fiction & fantasy' => ['science fiction', 'fantasy'],
        'science fiction fantasy' => ['science fiction', 'fantasy'],
        'sci fi & fantasy' => ['science fiction', 'fantasy'],
        'sci fi fantasy' => ['science fiction', 'fantasy'],
        'scifi & fantasy' => ['science fiction', 'fantasy'],
        'scifi fantasy' => ['science fiction', 'fantasy'],
        'scififantasy' => ['science fiction', 'fantasy'],
        'sciencefictionfantasy' => ['science fiction', 'fantasy'],
        'romantic comedy' => ['romance', 'comedy'],
        'romcom' => ['romance', 'comedy'],
        'rom com' => ['romance', 'comedy'],
        'action adventure' => ['action', 'adventure'],
        'action & adventure' => ['action', 'adventure'],
        'action adventure fiction' => ['action', 'adventure'],
        'actionadventure' => ['action', 'adventure'],
        'kids' => ['family'],
        'children' => ['family'],
        'childrens' => ['family'],
        'family friendly' => ['family'],
        'biopic' => ['biography', 'drama'],
        'biographical' => ['biography'],
        'film noir' => ['noir'],
        'docu series' => ['documentary'],
        'docuseries' => ['documentary'],
        'documentary series' => ['documentary'],
        'tv movie' => ['tv movie'],
        'television movie' => ['tv movie'],
        'tv special' => ['tv movie'],
        'mini series' => ['miniseries'],
    ];

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

    public function setGenresAttribute(array|string|null $value): void
    {
        if ($value === null) {
            $this->attributes['genres'] = null;

            return;
        }

        $normalized = $this->normalizeGenres($value);

        $this->attributes['genres'] = $normalized === []
            ? null
            : json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
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

    protected function newCollection(array $models = []): EloquentCollection
    {
        $collection = parent::newCollection($models);

        self::eagerLoadListRelations($collection);

        return $collection;
    }

    /**
     * @param  iterable<int, self>|self|null  $movies
     */
    public static function ensureListRelationsLoaded(iterable|self|null $movies): void
    {
        if ($movies === null) {
            return;
        }

        $collection = self::normalizeToEloquentCollection($movies);

        if ($collection === null) {
            return;
        }

        self::eagerLoadListRelations($collection);
    }

    protected static function eagerLoadListRelations(EloquentCollection $movies): void
    {
        if ($movies->isEmpty() || ! self::shouldEagerLoadListRelations()) {
            return;
        }

        $relations = self::listRelationLoaders();

        if ($relations === []) {
            return;
        }

        $movies->loadMissing($relations);
    }

    protected static function shouldEagerLoadListRelations(): bool
    {
        return (bool) config('flags.movie.list_eager_load.enabled', true);
    }

    /**
     * @return array<string, callable>
     */
    protected static function listRelationLoaders(): array
    {
        $definitions = self::normalizeRelationDefinitions(
            (array) config('flags.movie.list_eager_load.relations', [])
        );

        if ($definitions === []) {
            return [];
        }

        $loaders = [];

        foreach ($definitions as $definition) {
            $relation = $definition['relation'];
            $limit = $definition['limit'];

            if (! self::relationExists($relation)) {
                continue;
            }

            $loaders[$relation] = static fn ($query) => $query->limit($limit);
        }

        return $loaders;
    }

    /**
     * @param  array<mixed>  $definitions
     * @return array<int, array{relation: string, limit: int}>
     */
    protected static function normalizeRelationDefinitions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $key => $definition) {
            $relation = null;
            $limit = null;

            if (is_int($key)) {
                if (is_array($definition)) {
                    $relation = $definition['relation'] ?? null;
                    $limit = $definition['limit'] ?? null;
                }
            } elseif (is_string($key)) {
                if (is_array($definition)) {
                    $relation = $definition['relation'] ?? $key;
                    $limit = $definition['limit'] ?? null;
                } else {
                    $relation = $key;
                    $limit = $definition;
                }
            }

            if (! is_string($relation) || $relation === '') {
                continue;
            }

            $limit = is_numeric($limit) ? (int) $limit : null;

            if ($limit === null || $limit <= 0) {
                continue;
            }

            $normalized[] = [
                'relation' => $relation,
                'limit' => $limit,
            ];
        }

        return $normalized;
    }

    protected static function relationExists(string $relation): bool
    {
        if (method_exists(static::class, $relation)) {
            return true;
        }

        $class = static::class;

        return isset(static::$relationResolvers[$class][$relation]);
    }

    /**
     * @param  iterable<int, self>|self  $movies
     */
    protected static function normalizeToEloquentCollection(iterable|self $movies): ?EloquentCollection
    {
        if ($movies instanceof self) {
            return new EloquentCollection([$movies]);
        }

        if ($movies instanceof EloquentCollection) {
            return $movies;
        }

        if ($movies instanceof BaseCollection) {
            $movies = $movies->all();
        } elseif ($movies instanceof Traversable) {
            $movies = iterator_to_array($movies);
        }

        if (! is_array($movies)) {
            return null;
        }

        $filtered = array_values(array_filter($movies, static fn ($model): bool => $model instanceof self));

        if ($filtered === []) {
            return null;
        }

        return new EloquentCollection($filtered);
    }

    /**
     * @return HasMany<RecAbLog>
     */
    public function recAbLogs(): HasMany
    {
        return $this->hasMany(RecAbLog::class);
    }

    /**
     * @return HasMany<RecClick>
     */
    public function recClicks(): HasMany
    {
        return $this->hasMany(RecClick::class);
    }

    /**
     * @return HasMany<DeviceHistory>
     */
    public function deviceHistory(): HasMany
    {
        return $this->hasMany(DeviceHistory::class);
    }

    /**
     * @param  array<int, string>|string  $value
     * @return array<int, string>
     */
    private function toGenreArray(array|string $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $parts = preg_split('/[,;|]/', $value) ?: [];

        return array_map(static fn (string $part): string => trim($part), $parts);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeGenres(array|string $value): array
    {
        $genres = $this->toGenreArray($value);

        if ($genres === []) {
            return [];
        }

        $normalized = [];

        foreach ($genres as $genre) {
            if (! is_string($genre)) {
                continue;
            }

            foreach ($this->mapGenre($genre) as $mapped) {
                if ($mapped === '') {
                    continue;
                }

                if (! in_array($mapped, $normalized, true)) {
                    $normalized[] = $mapped;
                }
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function mapGenre(string $genre): array
    {
        $normalized = Str::of($genre)
            ->lower()
            ->replaceMatches('/[_.\/]/', ' ')
            ->replace('-', ' ')
            ->replace('&', ' & ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return [];
        }

        if (array_key_exists($normalized, self::GENRE_SYNONYMS)) {
            return self::GENRE_SYNONYMS[$normalized];
        }

        return [$normalized];
    }
}

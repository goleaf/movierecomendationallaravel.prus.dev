<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    private array $originalFlagConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalFlagConfig = (array) config('flags.movie.list_eager_load');

        Schema::create('movie_casts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('movie_posters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('url');
            $table->timestamps();
        });

        StubMovie::resolveRelationUsing('casts', static fn (StubMovie $movie) => $movie->hasMany(MovieCastStub::class));
        StubMovie::resolveRelationUsing('posters', static fn (StubMovie $movie) => $movie->hasMany(MoviePosterStub::class));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('movie_casts');
        Schema::dropIfExists('movie_posters');

        config()->set('flags.movie.list_eager_load', $this->originalFlagConfig);

        parent::tearDown();
    }

    public function test_movie_card_relations_are_eager_loaded_with_limits(): void
    {
        $relationsConfig = [
            ['relation' => 'casts', 'limit' => 3],
            ['relation' => 'posters', 'limit' => 2],
        ];

        config()->set('flags.movie.list_eager_load.relations', $relationsConfig);

        $movies = StubMovie::factory()->count(20)->create();

        $movies->each(function (StubMovie $movie): void {
            foreach (range(1, 5) as $index) {
                MovieCastStub::create([
                    'movie_id' => $movie->id,
                    'name' => 'Cast '.$index,
                ]);
            }

            foreach (range(1, 4) as $index) {
                MoviePosterStub::create([
                    'movie_id' => $movie->id,
                    'url' => 'https://example.com/poster-'.$index.'.jpg',
                ]);
            }
        });

        config()->set('flags.movie.list_eager_load.enabled', false);

        $baselineQueries = $this->measureQueryCount(function (): void {
            $baselineMovies = StubMovie::query()->orderBy('id')->limit(20)->get();

            $baselineMovies->each(function (StubMovie $movie): void {
                $movie->casts->take(2);
                $movie->posters->take(1);
            });
        });

        config()->set('flags.movie.list_eager_load.enabled', true);

        $optimizedMovies = null;
        $optimizedQueries = $this->measureQueryCount(function () use (&$optimizedMovies): void {
            $optimizedMovies = StubMovie::query()->orderBy('id')->limit(20)->get();

            $optimizedMovies->each(function (StubMovie $movie): void {
                $movie->casts->take(2);
                $movie->posters->take(1);
            });
        });

        self::assertGreaterThan(3, $baselineQueries);
        self::assertLessThanOrEqual(3, $optimizedQueries);
        self::assertGreaterThan($baselineQueries, $optimizedQueries);

        self::assertInstanceOf(Collection::class, $optimizedMovies);
        $first = $optimizedMovies->first();
        self::assertInstanceOf(StubMovie::class, $first);
        self::assertCount(3, $first->casts);
        self::assertCount(2, $first->posters);
    }

    private function measureQueryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $queries = DB::getQueryLog();

        DB::flushQueryLog();
        DB::disableQueryLog();

        return count($queries);
    }
}

class StubMovie extends Movie
{
    protected $table = 'movies';
}

class MovieCastStub extends Model
{
    protected $table = 'movie_casts';

    public $timestamps = false;

    protected $guarded = [];
}

class MoviePosterStub extends Model
{
    protected $table = 'movie_posters';

    public $timestamps = false;

    protected $guarded = [];
}

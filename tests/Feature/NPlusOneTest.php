<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\MovieCast;
use App\Models\MoviePoster;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_lists_eager_load_configured_relations(): void
    {
        config()->set('movies.list_relations.enabled', true);
        config()->set('movies.list_relations.relations', [
            'casts' => [
                'relation' => 'castMembers',
                'limit' => 2,
            ],
            'posters' => [
                'limit' => 1,
            ],
        ]);

        $movies = Movie::factory()->count(3)->create();

        $movies->each(function (Movie $movie): void {
            MovieCast::factory()
                ->count(5)
                ->for($movie)
                ->sequence(
                    ['order_column' => 0],
                    ['order_column' => 1],
                    ['order_column' => 2],
                    ['order_column' => 3],
                    ['order_column' => 4],
                )
                ->create();

            MoviePoster::factory()
                ->count(4)
                ->for($movie)
                ->sequence(
                    ['priority' => 0],
                    ['priority' => 1],
                    ['priority' => 2],
                    ['priority' => 3],
                )
                ->create();
        });

        $queryCount = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queryCount): void {
            // Ignore transaction savepoints that may be used by RefreshDatabase.
            if (str_contains(strtolower($query->sql), 'savepoint')) {
                return;
            }

            $queryCount++;
        });

        $indexResponse = $this->get(route('movies.index'));
        $indexResponse->assertOk();

        $this->assertSame(8, $queryCount, 'Unexpected number of queries while rendering the movie index.');

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<Movie> $paginator */
        $paginator = $indexResponse->viewData('movies');
        $collection = $paginator->getCollection();

        $collection->each(function (Movie $movie): void {
            $this->assertTrue($movie->relationLoaded('castMembers'));
            $this->assertCount(2, $movie->castMembers);
            $this->assertTrue($movie->relationLoaded('posters'));
            $this->assertCount(1, $movie->posters);
        });

        $queryCount = 0;

        $showResponse = $this->get(route('movies.show', $collection->first()));
        $showResponse->assertOk();
        $showResponse->assertViewHas('movie', function (Movie $movie): bool {
            return $movie->relationLoaded('castMembers')
                && $movie->castMembers->count() === 2
                && $movie->relationLoaded('posters')
                && $movie->posters->count() === 1;
        });

        $this->assertLessThanOrEqual(5, $queryCount, 'Show page should not trigger redundant relation queries.');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use App\Queries\MovieSearchQuery;
use App\Support\MovieSearchFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_case_insensitive_across_columns(): void
    {
        $match = Movie::factory()->create([
            'title' => 'MATRIX RESURGENCE',
            'plot' => 'A hacker dives back into the Matrix.',
        ]);

        Movie::factory()->create([
            'title' => 'Arrival',
            'plot' => 'Louise deciphers alien language',
        ]);

        $filters = new MovieSearchFilters('matrix', null, null, null, null);

        $results = MovieSearchQuery::forFilters($filters)->pluck('id')->all();

        $this->assertSame([$match->id], $results);
    }

    public function test_all_tokens_must_match_at_least_one_column(): void
    {
        $matching = Movie::factory()->create([
            'title' => 'Matrix Reloaded',
            'plot' => 'Neo fights Agent Smith inside the Matrix',
            'raw' => ['cast' => ['Keanu Reeves', 'Carrie-Anne Moss']],
        ]);

        Movie::factory()->create([
            'title' => 'Matrix Revolutions',
            'plot' => 'Morpheus leads the final assault',
        ]);

        $filters = new MovieSearchFilters('matrix neo', null, null, null, null);

        $results = MovieSearchQuery::forFilters($filters)->pluck('id')->all();

        $this->assertSame([$matching->id], $results);
    }

    public function test_search_matches_actor_names_with_diacritics_and_cyrillic(): void
    {
        $match = Movie::factory()->create([
            'title' => 'Hidden Voices',
            'plot' => 'An ensemble of multilingual actors uncovers a conspiracy.',
            'raw' => ['cast' => ['Zoë Kravitz', 'Андрей Смирнов']],
        ]);

        Movie::factory()->create([
            'title' => 'Galactic Heist',
            'plot' => 'A rogue pilot assembles a crew for the ultimate robbery.',
            'raw' => ['cast' => ['Pedro Pascal', 'Greta Lee']],
        ]);

        $filters = new MovieSearchFilters('zoë смирнов', null, null, null, null);

        $results = MovieSearchQuery::forFilters($filters)->pluck('id')->all();

        $this->assertSame([$match->id], $results);
    }

    public function test_year_range_filters_are_combined_via_where_all(): void
    {
        $within = Movie::factory()->create([
            'title' => 'Inception',
            'year' => 2012,
        ]);

        Movie::factory()->create([
            'title' => 'The Dark Knight',
            'year' => 2008,
        ]);

        Movie::factory()->create([
            'title' => 'Dune Part Two',
            'year' => 2024,
        ]);

        $filters = new MovieSearchFilters('', null, null, 2010, 2015);

        $results = MovieSearchQuery::forFilters($filters)->pluck('id')->all();

        $this->assertSame([$within->id], $results);
    }
}

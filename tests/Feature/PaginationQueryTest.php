<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaginationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_keep_all_query_parameters_on_movies_pagination(): void
    {
        Movie::factory()
            ->count(40)
            ->sequence(fn ($sequence) => [
                'title' => 'Matrix '.$sequence->index,
                'type' => 'movie',
                'genres' => ['action', 'thriller'],
                'year' => 1998 + ($sequence->index % 5),
            ])
            ->create();

        $response = $this->get('/movies?q=matrix&type=movie&sort=score&genres[]=action&genres[]=thriller&year_from=1995&year_to=2005');

        $response->assertOk();

        $nextUrl = $this->extractPaginationUrl($response->getContent(), 'movies-pagination', 'data-next');

        $this->assertNotNull($nextUrl, 'Expected movies pagination to have a next page.');

        $params = $this->parseQuery($nextUrl);

        $this->assertSame('2', $params['page']);
        $this->assertSame('matrix', $params['q']);
        $this->assertSame('movie', $params['type']);
        $this->assertSame('score', $params['sort']);
        $this->assertSame('1995', $params['year_from']);
        $this->assertSame('2005', $params['year_to']);
        $this->assertSame(['action', 'thriller'], $params['genres']);
    }

    public function test_recommendations_only_append_subset_of_query_parameters(): void
    {
        Movie::factory()->count(30)->create(['genres' => ['action']]);

        $response = $this->get('/movies?sort=recent&genres[]=action&year_from=2000&year_to=2020&extra=value');

        $response->assertOk();

        $nextUrl = $this->extractPaginationUrl($response->getContent(), 'recommendations-pagination', 'data-next');

        $this->assertNotNull($nextUrl, 'Expected recommendations pagination to have a next page.');

        $params = $this->parseQuery($nextUrl);

        $this->assertSame('2', $params['recommendations_page']);
        $this->assertSame('recent', $params['sort']);
        $this->assertSame('2000', $params['year_from']);
        $this->assertSame('2020', $params['year_to']);
        $this->assertSame(['action'], $params['genres']);
        $this->assertArrayNotHasKey('extra', $params);
    }

    public function test_empty_and_duplicate_values_are_removed_from_query_string(): void
    {
        Movie::factory()->count(30)->create(['genres' => ['drama']]);

        $response = $this->get('/movies?genres[]=drama&genres[]=&genres[]=drama&year_from=&year_to=2018');

        $response->assertOk();

        $nextUrl = $this->extractPaginationUrl($response->getContent(), 'movies-pagination', 'data-next');

        $this->assertNotNull($nextUrl, 'Expected movies pagination to have a next page.');

        $params = $this->parseQuery($nextUrl);

        $this->assertSame(['drama'], $params['genres']);
        $this->assertArrayNotHasKey('year_from', $params);
        $this->assertSame('2018', $params['year_to']);
    }

    public function test_sort_links_preserve_existing_filters(): void
    {
        Movie::factory()->count(1)->create(['genres' => ['comedy']]);

        $response = $this->get('/movies?genres[]=comedy&year_to=2010&sort=oldest');

        $response->assertOk();

        $recentSortUrl = $this->extractHref($response->getContent(), 'sort-option-recent');

        $this->assertNotNull($recentSortUrl, 'Sort link for "recent" should be present.');

        $params = $this->parseQuery($recentSortUrl);

        $this->assertSame('recent', $params['sort']);
        $this->assertSame(['comedy'], $params['genres']);
        $this->assertSame('2010', $params['year_to']);
    }

    private function extractPaginationUrl(string $html, string $testId, string $attribute): ?string
    {
        $pattern = sprintf('/data-testid="%s"[^>]*%s="([^"]*)"/i', preg_quote($testId, '/'), preg_quote($attribute, '/'));

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $value = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);

        return $value === '' ? null : $value;
    }

    private function extractHref(string $html, string $testId): ?string
    {
        $patterns = [
            sprintf('/<[^>]*data-testid="%s"[^>]*href="([^"]*)"/i', preg_quote($testId, '/')),
            sprintf('/<[^>]*href="([^"]*)"[^>]*data-testid="%s"/i', preg_quote($testId, '/')),
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseQuery(string $url): array
    {
        $queryString = parse_url($url, PHP_URL_QUERY) ?? '';
        $params = [];
        parse_str($queryString, $params);

        return $params;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class AbstractMovieFeedController extends Controller
{
    protected const int PER_PAGE = 30;

    final public function __invoke(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));

        $baseQuery = $this->baseQuery();

        $lastModifiedAt = $this->resolveLastModified($baseQuery);

        $movies = $this->getMovies(clone $baseQuery, $page);
        $etag = $this->makeEtag($lastModifiedAt, $page, $movies);

        $items = $movies->map(function (Movie $movie): array {
            return $this->transformMovie($movie);
        })->values()->all();

        $response = response()
            ->view('rss.feed', [
                'title' => $this->feedTitle(),
                'description' => $this->feedDescription(),
                'link' => url()->current(),
                'selfLink' => $this->buildSelfLink($request, $page),
                'language' => 'ru-RU',
                'lastBuildDate' => $lastModifiedAt ?? CarbonImmutable::now('UTC'),
                'items' => $items,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');

        if ($lastModifiedAt !== null) {
            $response->setLastModified($lastModifiedAt);
        }

        $response->setEtag($etag, true);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    abstract protected function baseQuery(): Builder;

    abstract protected function applyOrdering(Builder $query): Builder;

    abstract protected function feedTitle(): string;

    abstract protected function feedDescription(): string;

    protected function transformMovie(Movie $movie): array
    {
        $translations = $movie->translations ?? [];

        $title = (string) data_get($translations, 'title.ru', $movie->title);
        $plot = (string) data_get($translations, 'plot.ru', $movie->plot ?? '');
        $plot = (string) Str::of($plot)->squish();
        $description = $plot === '' ? null : Str::limit($plot, 360);

        $publishedAt = $movie->release_date
            ?? $movie->updated_at?->toImmutable()
            ?? CarbonImmutable::now('UTC');

        return [
            'title' => $title,
            'description' => $description,
            'link' => route('movies.show', ['movie' => $movie->getKey()]),
            'guid' => sprintf('movie:%s', $movie->imdb_tt),
            'pubDate' => $publishedAt?->setTimezone('UTC'),
            'categories' => is_array($movie->genres) ? array_values($movie->genres) : [],
        ];
    }

    protected function resolveLastModified(Builder $query): ?CarbonImmutable
    {
        $timestamp = (clone $query)->max($this->lastModifiedColumn());

        if ($timestamp === null) {
            return null;
        }

        return CarbonImmutable::parse($timestamp)->setTimezone('UTC');
    }

    protected function lastModifiedColumn(): string
    {
        return 'updated_at';
    }

    protected function getMovies(Builder $query, int $page): Collection
    {
        return $this->applyOrdering($query)
            ->forPage($page, self::PER_PAGE)
            ->get();
    }

    protected function makeEtag(?CarbonImmutable $lastModifiedAt, int $page, Collection $movies): string
    {
        $fingerprint = implode('|', [
            static::class,
            (string) $page,
            $lastModifiedAt?->format('U.u') ?? 'none',
            $movies->map(function (Movie $movie): string {
                $updatedAt = $movie->updated_at?->format('U.u') ?? '0';

                return sprintf('%d:%s', $movie->getKey(), $updatedAt);
            })->implode(','),
        ]);

        return sha1($fingerprint);
    }

    protected function buildSelfLink(Request $request, int $page): string
    {
        if ($request->query->count() === 0 && $page === 1) {
            return $request->url();
        }

        return $request->fullUrlWithQuery(['page' => $page]);
    }
}

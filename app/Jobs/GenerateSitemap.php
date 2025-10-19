<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Movie;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\CarbonImmutable;
use Illuminate\Support\Str;

class GenerateSitemap
{
    use Dispatchable;

    /**
     * @return array<string, array{lastmod: CarbonImmutable, items: array<int, array{loc: string, lastmod?: CarbonImmutable|null, changefreq?: string|null}>}>
     */
    public function handle(): array
    {
        $generatedAt = now()->toImmutable();

        $sections = [];

        $staticItems = $this->buildStaticItems($generatedAt);

        if ($staticItems !== []) {
            $sections['static'] = [
                'lastmod' => $this->resolveLastModification($staticItems, $generatedAt),
                'items' => $staticItems,
            ];
        }

        foreach ($this->buildMovieSections($generatedAt) as $key => $section) {
            $sections[$key] = $section;
        }

        return $sections;
    }

    /**
     * @return array<int, array{loc: string, lastmod: CarbonImmutable, changefreq: string}>
     */
    private function buildStaticItems(CarbonImmutable $generatedAt): array
    {
        return [
            [
                'loc' => route('home'),
                'lastmod' => $generatedAt,
                'changefreq' => 'daily',
            ],
            [
                'loc' => route('search'),
                'lastmod' => $generatedAt,
                'changefreq' => 'weekly',
            ],
            [
                'loc' => route('trends'),
                'lastmod' => $generatedAt,
                'changefreq' => 'weekly',
            ],
            [
                'loc' => route('works'),
                'lastmod' => $generatedAt,
                'changefreq' => 'monthly',
            ],
            [
                'loc' => route('landing-page'),
                'lastmod' => $generatedAt,
                'changefreq' => 'monthly',
            ],
            [
                'loc' => route('contact.form'),
                'lastmod' => $generatedAt,
                'changefreq' => 'monthly',
            ],
        ];
    }

    /**
     * @return array<string, array{lastmod: CarbonImmutable, items: array<int, array{loc: string, lastmod?: CarbonImmutable|null, changefreq?: string|null}>}>
     */
    private function buildMovieSections(CarbonImmutable $generatedAt): array
    {
        $sections = [];

        $types = Movie::query()
            ->select('type')
            ->selectRaw('MAX(updated_at) as last_updated_at')
            ->groupBy('type')
            ->orderBy('type')
            ->get();

        foreach ($types as $typeData) {
            $type = (string) $typeData->type;
            $slug = Str::slug($type);
            $key = $slug !== '' ? "movies-{$slug}" : 'movies-'.md5($type);

            $items = [];

            foreach (
                Movie::query()
                    ->where('type', $type)
                    ->select(['id', 'updated_at'])
                    ->orderByDesc('updated_at')
                    ->lazy() as $movie
            ) {
                $items[] = [
                    'loc' => route('movies.show', ['movie' => $movie->getRouteKey()]),
                    'lastmod' => $movie->updated_at?->toImmutable(),
                    'changefreq' => 'weekly',
                ];
            }

            if ($items === []) {
                continue;
            }

            $sections[$key] = [
                'lastmod' => $typeData->last_updated_at !== null
                    ? CarbonImmutable::parse($typeData->last_updated_at, config('app.timezone'))
                    : $this->resolveLastModification($items, $generatedAt),
                'items' => $items,
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int, array{loc: string, lastmod?: CarbonImmutable|null, changefreq?: string|null}>  $items
     */
    private function resolveLastModification(array $items, CarbonImmutable $fallback): CarbonImmutable
    {
        $latest = null;

        foreach ($items as $item) {
            $lastmod = $item['lastmod'] ?? null;

            if (! $lastmod instanceof CarbonImmutable) {
                continue;
            }

            if ($latest === null || $lastmod->greaterThan($latest)) {
                $latest = $lastmod;
            }
        }

        return $latest ?? $fallback;
    }
}

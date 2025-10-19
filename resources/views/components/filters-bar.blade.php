@php
    $activeGenre = request()->query('genre');
    $activeYear = request()->integer('year');
    $movieFromRoute = request()->route('movie');

    $searchLink = route('search', array_filter([
        'genre' => $activeGenre,
        'year' => $activeYear,
        'type' => 'movie',
    ]));

    $seriesLink = request()->fullUrlWithQuery([
        'type' => 'series',
    ]);
@endphp

<div class="flex flex-col gap-2 rounded-md bg-slate-900/80 p-4 text-sm text-slate-200">
    <div data-testid="filters-genre">Genre: {{ $activeGenre ?? 'not selected' }}</div>
    <div data-testid="filters-year">Year: {{ $activeYear ?? 'not selected' }}</div>

    <div class="flex flex-wrap gap-3 pt-2 text-xs uppercase tracking-wide text-slate-400">
        <a data-testid="filters-movie-link" class="font-semibold text-amber-300 hover:text-amber-200" href="{{ $searchLink }}">
            View as movies (keeps ?genre=&year=)
        </a>
        <a data-testid="filters-series-link" class="font-semibold text-blue-300 hover:text-blue-200" href="{{ $seriesLink }}">
            View as series on this page
        </a>
    </div>

    <div data-testid="filters-route" class="pt-2 text-slate-400">
        Movie from route parameter: {{ $movieFromRoute ?? 'not in movie context' }}
    </div>
</div>

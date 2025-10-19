<div class="space-y-10">
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-50">{{ __('messages.home.recommendations_heading') }}</h1>
            <p class="mt-2 text-sm text-slate-400">{{ __('messages.home.recommendations_description') }}</p>
        </div>

        @if ($recommended->isEmpty())
            <p class="text-sm text-slate-400">{{ __('messages.home.empty_recommendations') }}</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($recommended as $movie)
                    <a
                        wire:key="recommended-{{ $movie->id }}"
                        href="{{ route('movies.show', $movie) }}"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition duration-200 hover:border-slate-700 hover:bg-slate-900"
                    >
                        @if ($movie->poster_url)
                            <img
                                src="{{ $movie->poster_url }}"
                                alt="{{ $movie->title ? __('messages.common.poster_alt', ['title' => $movie->title]) : __('messages.common.poster_alt_generic') }}"
                                loading="lazy"
                                class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                            />
                        @endif

                        <div class="space-y-1">
                            <p class="text-base font-semibold text-slate-50">{{ $movie->title }} <span class="font-normal text-slate-400">({{ $movie->year ?? __('messages.common.dash') }})</span></p>
                            <p class="text-sm text-slate-400">{{ __('messages.common.imdb_with_votes_colon', ['rating' => $movie->imdb_rating ?? __('messages.common.dash'), 'votes' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-50">{{ __('messages.home.trends_heading') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{!! __('messages.home.trends_description_html', ['url' => route('trends')]) !!}</p>
        </div>

        @if ($trending->isEmpty())
            <p class="text-sm text-slate-400">{{ __('messages.home.empty_trending') }}</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($trending as $row)
                    @php($movie = $row['movie'])

                    <a
                        wire:key="trending-{{ $movie->id }}"
                        href="{{ route('movies.show', $movie) }}"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition duration-200 hover:border-slate-700 hover:bg-slate-900"
                    >
                        @if ($movie->poster_url)
                            <img
                                src="{{ $movie->poster_url }}"
                                alt="{{ $movie->title ? __('messages.common.poster_alt', ['title' => $movie->title]) : __('messages.common.poster_alt_generic') }}"
                                loading="lazy"
                                class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                            />
                        @endif

                        <div class="space-y-1">
                            <p class="text-base font-semibold text-slate-50">{{ $movie->title }} <span class="font-normal text-slate-400">({{ $movie->year ?? __('messages.common.dash') }})</span></p>
                            <p class="text-sm text-slate-400">
                                @if (!is_null($row['clicks']))
                                    {{ __('messages.common.clicks', ['count' => number_format($row['clicks'], 0, '.', ' ')]) }}
                                    @if ($movie->imdb_rating)
                                        • {{ __('messages.common.imdb_only', ['rating' => $movie->imdb_rating]) }}
                                    @endif
                                @else
                                    {{ __('messages.common.imdb_with_votes', ['rating' => $movie->imdb_rating ?? __('messages.common.dash'), 'votes' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]) }}
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>

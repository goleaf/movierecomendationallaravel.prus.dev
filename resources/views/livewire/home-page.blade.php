<div class="space-y-10">
    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-50">Персональные рекомендации</h1>
            <p class="mt-2 text-sm text-slate-400">Алгоритм A/B (device cookie) подбирает топ релизы по IMDb и свежести.</p>
        </div>

        @if ($recommended->isEmpty())
            <p class="text-sm text-slate-400">Пока нет данных для рекомендаций.</p>
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
                                src="{{ proxy_image_url($movie->poster_url) ?? $movie->poster_url }}"
                                alt="{{ $movie->title ? 'Постер фильма «' . $movie->title . '»' : 'Постер фильма' }}"
                                loading="lazy"
                                class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                            />
                        @endif

                        <div class="space-y-1">
                            <p class="text-base font-semibold text-slate-50">{{ $movie->title }} <span class="font-normal text-slate-400">({{ $movie->year ?? '—' }})</span></p>
                            <p class="text-sm text-slate-400">IMDb: {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-50">Тренды за 7 дней</h2>
            <p class="mt-2 text-sm text-slate-400">Клики рекомендаций по placement'ам. Подробнее — <a class="text-sky-300 hover:text-sky-200" href="{{ route('trends') }}">страница трендов</a>.</p>
        </div>

        @if ($trending->isEmpty())
            <p class="text-sm text-slate-400">Статистика кликов пока не собрана.</p>
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
                                src="{{ proxy_image_url($movie->poster_url) ?? $movie->poster_url }}"
                                alt="{{ $movie->title ? 'Постер фильма «' . $movie->title . '»' : 'Постер фильма' }}"
                                loading="lazy"
                                class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                            />
                        @endif

                        <div class="space-y-1">
                            <p class="text-base font-semibold text-slate-50">{{ $movie->title }} <span class="font-normal text-slate-400">({{ $movie->year ?? '—' }})</span></p>
                            <p class="text-sm text-slate-400">
                                @if (!is_null($row['clicks']))
                                    Клики: {{ $row['clicks'] }}
                                    @if ($movie->imdb_rating)
                                        • IMDb {{ $movie->imdb_rating }}
                                    @endif
                                @else
                                    IMDb {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }}
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>

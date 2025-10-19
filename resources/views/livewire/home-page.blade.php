<section class="space-y-10">
    <div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-8 shadow-sm shadow-slate-900/40">
        <h1 class="text-3xl font-semibold text-white">Персональные рекомендации</h1>
        <p class="mt-2 text-sm text-slate-400">
            Алгоритм A/B подбирает свежие релизы с высоким рейтингом IMDb и персональными сигналами устройства.
        </p>
    </div>

    @if($recommended->isEmpty())
        <p class="text-sm text-slate-400">Пока нет данных для рекомендаций.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($recommended as $movie)
                <a
                    wire:key="rec-{{ $movie->id }}"
                    href="{{ route('movies.show', $movie) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/50 transition hover:border-slate-700 hover:bg-slate-900"
                >
                    @if($movie->poster_url)
                        <div class="aspect-[2/3] w-full overflow-hidden bg-slate-950">
                            <img
                                alt="{{ $movie->title }}"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                                src="{{ $movie->poster_url }}"
                            >
                        </div>
                    @endif

                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <div>
                            <p class="text-lg font-semibold text-white">{{ $movie->title }}</p>
                            <p class="text-sm text-slate-400">{{ $movie->year ?? '—' }}</p>
                        </div>
                        <p class="text-sm text-slate-400">
                            IMDb: {{ $movie->imdb_rating ?? '—' }} •
                            {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }} голосов
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-8 shadow-sm shadow-slate-900/40">
        <h2 class="text-2xl font-semibold text-white">Тренды кликов за 7 дней</h2>
        <p class="mt-2 text-sm text-slate-400">
            Сводка по кликам рекомендаций в ленте. Подробнее —
            <a class="text-sky-400 underline-offset-4 hover:underline" href="{{ route('trends') }}">страница трендов</a>.
        </p>
    </div>

    @if($trending->isEmpty())
        <p class="text-sm text-slate-400">Статистика кликов пока не собрана.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($trending as $row)
                @php($movie = $row['movie'])

                <a
                    wire:key="trending-{{ $movie->id }}"
                    href="{{ route('movies.show', $movie) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/50 transition hover:border-slate-700 hover:bg-slate-900"
                >
                    @if($movie->poster_url)
                        <div class="aspect-[2/3] w-full overflow-hidden bg-slate-950">
                            <img
                                alt="{{ $movie->title }}"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                                src="{{ $movie->poster_url }}"
                            >
                        </div>
                    @endif

                    <div class="flex flex-1 flex-col gap-2 p-5">
                        <div>
                            <p class="text-lg font-semibold text-white">{{ $movie->title }}</p>
                            <p class="text-sm text-slate-400">{{ $movie->year ?? '—' }}</p>
                        </div>
                        <p class="text-sm text-slate-400">
                            @if(!is_null($row['clicks']))
                                Клики: {{ $row['clicks'] }}
                                @if($movie->imdb_rating)
                                    • IMDb {{ $movie->imdb_rating }}
                                @endif
                            @else
                                IMDb {{ $movie->imdb_rating ?? '—' }} •
                                {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }} голосов
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>

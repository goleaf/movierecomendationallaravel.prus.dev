<div class="space-y-10">
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-50">Тренды рекомендаций</h1>
        <p class="mt-2 text-sm text-slate-400">Период: {{ $from }} — {{ $to }} ({{ $days }} дн.)</p>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-6">
            <div class="md:col-span-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Период</label>
                <select wire:model.live="days" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                    @foreach ([3, 7, 14, 30] as $option)
                        <option value="{{ $option }}">{{ $option }} дней</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Тип</label>
                <select wire:model.live="type" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                    <option value="">Все</option>
                    <option value="movie">Фильмы</option>
                    <option value="series">Сериалы</option>
                    <option value="animation">Мультики</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Жанр</label>
                <input
                    wire:model.live.debounce.500ms="genre"
                    type="text"
                    placeholder="sci-fi, drama, comedy"
                    class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                />
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Год от</label>
                <input
                    wire:model.live="yf"
                    type="number"
                    min="0"
                    class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                />
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Год до</label>
                <input
                    wire:model.live="yt"
                    type="number"
                    min="0"
                    class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                />
            </div>
        </div>
    </section>

    @if ($items->isEmpty())
        <p class="text-sm text-slate-400">Нет данных — проверьте сбор кликов или измените фильтры.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($items as $item)
                <a
                    wire:key="trend-item-{{ $item['id'] }}"
                    href="{{ route('movies.show', ['movie' => $item['id']]) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition duration-200 hover:border-slate-700 hover:bg-slate-900"
                >
                    @if ($poster = proxy_image_url($item['poster_url']))
                        <img
                            src="{{ $poster }}"
                            alt="{{ $item['title'] }}"
                            loading="lazy"
                            class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                        />
                    @endif

                    <div class="space-y-1">
                        <p class="text-base font-semibold text-slate-50">{{ $item['title'] }} <span class="font-normal text-slate-400">({{ $item['year'] ?? '—' }})</span></p>
                        <p class="text-sm text-slate-400">
                            Клики: {{ $item['clicks'] ?? '—' }}
                            @if (!empty($item['imdb_rating']))
                                • IMDb {{ $item['imdb_rating'] }}
                            @endif
                            @if (!empty($item['imdb_votes']))
                                • {{ number_format($item['imdb_votes'], 0, '.', ' ') }} голосов
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>

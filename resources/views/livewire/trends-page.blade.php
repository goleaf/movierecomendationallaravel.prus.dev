<div class="space-y-10">
    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-50">{{ __('messages.trends.heading') }}</h1>
        <p class="mt-2 text-sm text-slate-400">{{ __('messages.trends.period', ['from' => $from, 'to' => $to, 'days' => $days, 'days_short' => __('messages.trends.days_short')]) }}</p>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-6">
            <div class="md:col-span-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('messages.trends.filters.period') }}</label>
                <select wire:model.live="days" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                    @foreach ([3, 7, 14, 30] as $option)
                        <option value="{{ $option }}">{{ trans_choice('messages.trends.filters.days_option', $option, ['count' => $option]) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('messages.trends.filters.type') }}</label>
                <select wire:model.live="type" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                    <option value="">{{ __('messages.trends.filters.any') }}</option>
                    <option value="movie">{{ __('messages.search.form.type_movie') }}</option>
                    <option value="series">{{ __('messages.search.form.type_series') }}</option>
                    <option value="animation">{{ __('messages.search.form.type_animation') }}</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('messages.trends.filters.genre') }}</label>
                <input
                    wire:model.live.debounce.500ms="genre"
                    type="text"
                    placeholder="{{ __('messages.trends.filters.genre_hint') }}"
                    class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                />
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('messages.trends.filters.year_from') }}</label>
                <input
                    wire:model.live="yf"
                    type="number"
                    min="0"
                    class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                />
            </div>

            <div>
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('messages.trends.filters.year_to') }}</label>
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
        <p class="text-sm text-slate-400">{{ __('messages.trends.empty') }}</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($items as $item)
                <a
                    wire:key="trend-item-{{ $item['id'] }}"
                    href="{{ route('movies.show', ['movie' => $item['id']]) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition duration-200 hover:border-slate-700 hover:bg-slate-900"
                >
                    @if ($item['poster_url'])
                        <img
                            src="{{ $item['poster_url'] }}"
                            alt="{{ !empty($item['title']) ? __('messages.common.poster_alt', ['title' => $item['title']]) : __('messages.common.poster_alt_generic') }}"
                            loading="lazy"
                            class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"
                        />
                    @endif

                    <div class="space-y-1">
                        <p class="text-base font-semibold text-slate-50">{{ $item['title'] }} <span class="font-normal text-slate-400">({{ $item['year'] ?? __('messages.common.dash') }})</span></p>
                        <p class="text-sm text-slate-400">
                            {{ __('messages.common.clicks', ['count' => isset($item['clicks']) ? number_format($item['clicks'], 0, '.', ' ') : __('messages.common.dash')]) }}
                            @if (!empty($item['imdb_rating']))
                                • {{ __('messages.common.imdb_only', ['rating' => $item['imdb_rating']]) }}
                            @endif
                            @if (!empty($item['imdb_votes']))
                                • {{ __('messages.trends.votes', ['count' => number_format($item['imdb_votes'], 0, '.', ' ')]) }}
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>

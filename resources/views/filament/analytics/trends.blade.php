<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-3xl border border-white/10 bg-white/10 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Тренды рекомендаций</h1>
                <p class="text-sm text-white/70">Период: {{ $fromDate }} — {{ $toDate }} ({{ $days }} дн.)</p>
            </div>
            <label class="text-sm text-white/70">
                Период выборки
                <select wire:model.live="days" class="ml-3 rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    @foreach([3, 7, 14, 30] as $option)
                        <option value="{{ $option }}">{{ $option }} дн.</option>
                    @endforeach
                </select>
            </label>
        </div>

        @if($items->isEmpty())
            <p class="rounded-3xl border border-white/10 bg-slate-950/50 p-6 text-sm text-white/60">Нет данных для выбранного периода.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($items as $item)
                    <div class="flex flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950/50">
                        @if($item['poster_url'])
                            <div class="aspect-[2/3] w-full overflow-hidden bg-slate-950">
                                <img alt="{{ $item['title'] }}" class="h-full w-full object-cover" loading="lazy" src="{{ $item['poster_url'] }}">
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col gap-3 p-5">
                            <div>
                                <h3 class="text-lg font-semibold text-white">{{ $item['title'] }}</h3>
                                <p class="text-sm text-white/60">{{ $item['year'] ?? '—' }}</p>
                            </div>
                            <p class="text-sm text-white/70">
                                Клики: {{ $item['clicks'] ?? '—' }}
                                @if(!empty($item['imdb_rating']))
                                    • IMDb {{ $item['imdb_rating'] }}
                                @endif
                                @if(!empty($item['imdb_votes']))
                                    • {{ number_format($item['imdb_votes'], 0, '.', ' ') }} голосов
                                @endif
                            </p>
                            <a href="{{ route('movies.show', ['movie' => $item['id']]) }}" class="mt-auto inline-flex items-center justify-center rounded-xl border border-sky-400/50 bg-sky-500/20 px-3 py-2 text-sm font-medium text-sky-200 transition hover:bg-sky-500/40">Открыть фильм</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>

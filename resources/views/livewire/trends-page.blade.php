<section class="space-y-10">
    <div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-8 shadow-sm shadow-slate-900/40">
        <h1 class="text-3xl font-semibold text-white">Тренды рекомендаций</h1>
        <p class="mt-2 text-sm text-slate-400">
            Период: {{ $fromDate }} — {{ $toDate }} ({{ $days }} дн.)
        </p>
    </div>

    <form wire:submit.prevent="applyFilters" class="grid gap-4 rounded-3xl border border-slate-800 bg-slate-900/60 p-6 shadow-sm shadow-slate-900/40 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Период</label>
            <select wire:model.defer="days" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                @foreach([3, 7, 14, 30] as $option)
                    <option value="{{ $option }}">{{ $option }} дн.</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Тип</label>
            <select wire:model.defer="type" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                @foreach($availableTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Жанр</label>
            <input wire:model.defer="genre" type="text" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="sci-fi">
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Год от</label>
            <input wire:model.defer="yf" type="number" min="1900" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Год до</label>
            <input wire:model.defer="yt" type="number" min="1900" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>

        <div class="flex items-end lg:col-span-1">
            <button type="submit" class="w-full rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">Показать</button>
        </div>
    </form>

    <div class="min-h-[200px]">
        <div wire:loading.flex class="flex items-center justify-center gap-2 rounded-3xl border border-slate-800 bg-slate-900/60 p-6 text-sm text-slate-400 shadow-sm shadow-slate-900/40">
            <svg class="h-5 w-5 animate-spin text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Загружаем тренды…
        </div>

        @if($items->isEmpty())
            <p wire:loading.remove class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6 text-sm text-slate-400 shadow-sm shadow-slate-900/40">
                Нет данных — проверьте сбор кликов или измените фильтры.
            </p>
        @else
            <div wire:loading.remove class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($items as $item)
                    <a
                        wire:key="trend-{{ $item['id'] }}"
                        href="{{ route('movies.show', ['movie' => $item['id']]) }}"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/50 transition hover:border-slate-700 hover:bg-slate-900"
                    >
                        @if($item['poster_url'])
                            <div class="aspect-[2/3] w-full overflow-hidden bg-slate-950">
                                <img
                                    alt="{{ $item['title'] }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    loading="lazy"
                                    src="{{ $item['poster_url'] }}"
                                >
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col gap-2 p-5">
                            <div>
                                <p class="text-lg font-semibold text-white">{{ $item['title'] }}</p>
                                <p class="text-sm text-slate-400">{{ $item['year'] ?? '—' }}</p>
                            </div>
                            <p class="text-sm text-slate-400">
                                Клики: {{ $item['clicks'] ?? '—' }}
                                @if(!empty($item['imdb_rating']))
                                    • IMDb {{ $item['imdb_rating'] }}
                                @endif
                                @if(!empty($item['imdb_votes']))
                                    • {{ number_format($item['imdb_votes'], 0, '.', ' ') }} голосов
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

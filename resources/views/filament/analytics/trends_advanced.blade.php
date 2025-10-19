<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            <h1 class="text-2xl font-semibold text-white">Расширенные фильтры трендов</h1>
            <p class="mt-2 text-sm text-white/70">Период: {{ $fromDate }} — {{ $toDate }} ({{ $days }} дн.)</p>
        </div>

        <form wire:submit.prevent="applyFilters" class="grid gap-4 rounded-3xl border border-white/10 bg-slate-950/40 p-6 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-white/60">Период</label>
                <select wire:model.defer="days" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    @foreach([3, 7, 14, 30] as $option)
                        <option value="{{ $option }}">{{ $option }} дн.</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-white/60">Тип</label>
                <select wire:model.defer="type" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    @foreach($availableTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-white/60">Жанр</label>
                <input wire:model.defer="genre" type="text" placeholder="drama" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>

            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-white/60">Год от</label>
                <input wire:model.defer="yf" type="number" min="1900" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>

            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold uppercase tracking-wide text-white/60">Год до</label>
                <input wire:model.defer="yt" type="number" min="1900" class="mt-2 w-full rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            </div>

            <div class="flex items-end lg:col-span-1">
                <button type="submit" class="w-full rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">Показать</button>
            </div>
        </form>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            @if($items->isEmpty())
                <p class="text-sm text-white/60">Нет данных по заданным фильтрам.</p>
            @else
                <div class="overflow-hidden rounded-2xl border border-white/10">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-white">
                        <thead class="bg-white/5 text-xs uppercase text-white/60">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Название</th>
                                <th class="px-4 py-3 font-semibold">Год</th>
                                <th class="px-4 py-3 font-semibold">Тип</th>
                                <th class="px-4 py-3 font-semibold">Клики</th>
                                <th class="px-4 py-3 font-semibold">IMDb</th>
                                <th class="px-4 py-3 font-semibold">Действие</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 bg-slate-950/40">
                            @foreach($items as $item)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $item['title'] }}</td>
                                    <td class="px-4 py-3">{{ $item['year'] ?? '—' }}</td>
                                    <td class="px-4 py-3 uppercase text-white/60">{{ $item['type'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['clicks'] ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if(!empty($item['imdb_rating']))
                                            {{ $item['imdb_rating'] }}
                                            @if(!empty($item['imdb_votes']))
                                                <span class="text-xs text-white/50">({{ number_format($item['imdb_votes'], 0, '.', ' ') }})</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('movies.show', ['movie' => $item['id']]) }}" class="inline-flex items-center justify-center rounded-lg border border-sky-400/50 bg-sky-500/20 px-3 py-1 text-xs font-medium text-sky-200 transition hover:bg-sky-500/40">Открыть</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div wire:loading.flex class="fixed inset-0 z-10 flex items-center justify-center bg-slate-950/60 backdrop-blur">
        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950 px-5 py-3 text-sm text-white/80">
            <svg class="h-5 w-5 animate-spin text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Загружаем отчёт…
        </div>
    </div>
</x-filament-panels::page>

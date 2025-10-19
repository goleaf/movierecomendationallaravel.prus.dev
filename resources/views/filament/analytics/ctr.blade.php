<x-filament-panels::page>
    <form wire:submit.prevent="applyFilters" class="grid gap-4 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-white/60">С даты</label>
            <input wire:model.defer="from" type="date" class="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-white/60">По дату</label>
            <input wire:model.defer="to" type="date" class="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/40 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-white/60">Плейсмент</label>
            <select wire:model.defer="placement" class="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Все</option>
                @foreach($availablePlacements as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-1">
            <label class="block text-xs font-medium uppercase tracking-wide text-white/60">Вариант</label>
            <select wire:model.defer="variant" class="mt-2 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">A/B</option>
                <option value="A">A</option>
                <option value="B">B</option>
            </select>
        </div>

        <div class="flex items-end lg:col-span-1">
            <button type="submit" class="w-full rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">Обновить</button>
        </div>
    </form>

    <div class="grid gap-6 py-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            <h2 class="text-lg font-semibold text-white">CTR по вариантам</h2>
            <div class="mt-4 grid gap-3">
                @forelse($summary as $row)
                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-white">Вариант {{ $row['variant'] }}</p>
                            <p class="text-xs text-white/60">Imps: {{ number_format($row['imps'], 0, '.', ' ') }} • Clicks: {{ number_format($row['clks'], 0, '.', ' ') }}</p>
                        </div>
                        <span class="text-xl font-semibold text-sky-400">{{ number_format($row['ctr'], 2) }}%</span>
                    </div>
                @empty
                    <p class="text-sm text-white/60">Данных не найдено за выбранный период.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            <h2 class="text-lg font-semibold text-white">Клики по плейсментам</h2>
            <div class="mt-4 space-y-3">
                @forelse($clicksByPlacement as $row)
                    <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3">
                        <span class="text-sm font-medium text-white">{{ $row['placement'] }}</span>
                        <span class="text-sm text-white/70">{{ number_format($row['clicks'], 0, '.', ' ') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-white/60">Нет кликов в заданном диапазоне.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-white">Линейный CTR по дням</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60">
                <img
                    alt="CTR line chart"
                    class="w-full"
                    src="{{ route('admin.ctr.svg', ['from' => $from, 'to' => $to]) }}"
                >
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-white">CTR по плейсментам и вариантам</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60">
                <img
                    alt="CTR bar chart"
                    class="w-full"
                    src="{{ route('admin.ctr.bars.svg', ['from' => $from, 'to' => $to]) }}"
                >
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 lg:col-span-2">
            <h2 class="text-lg font-semibold text-white">Воронка просмотров</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                <table class="min-w-full divide-y divide-white/5 text-left text-sm text-white">
                    <thead class="bg-white/5 text-xs uppercase text-white/60">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Шаг</th>
                            <th class="px-4 py-3 font-semibold">Imps</th>
                            <th class="px-4 py-3 font-semibold">Clicks</th>
                            <th class="px-4 py-3 font-semibold">Views</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 bg-slate-950/40">
                        @forelse($funnels as $row)
                            <tr>
                                <td class="px-4 py-3 capitalize">{{ $row['label'] }}</td>
                                <td class="px-4 py-3">{{ number_format($row['imps'], 0, '.', ' ') }}</td>
                                <td class="px-4 py-3">{{ number_format($row['clks'], 0, '.', ' ') }}</td>
                                <td class="px-4 py-3">{{ number_format($row['views'], 0, '.', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-3 text-white/60" colspan="4">Недостаточно данных для построения воронки.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div wire:loading.flex class="fixed inset-0 z-10 flex items-center justify-center bg-slate-950/60 backdrop-blur">
        <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-slate-950 px-5 py-3 text-sm text-white/80">
            <svg class="h-5 w-5 animate-spin text-sky-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Обновляем данные…
        </div>
    </div>
</x-filament-panels::page>

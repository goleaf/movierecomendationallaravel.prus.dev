<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">От</label>
                    <input
                        type="date"
                        wire:model.live="from"
                        class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">До</label>
                    <input
                        type="date"
                        wire:model.live="to"
                        class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Площадка</label>
                    <select
                        wire:model.live="placement"
                        class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                    >
                        <option value="">Все</option>
                        <option value="home">home</option>
                        <option value="show">show</option>
                        <option value="trends">trends</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Вариант</label>
                    <select
                        wire:model.live="variant"
                        class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
                    >
                        <option value="">A + B</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="refreshReport"
                        class="inline-flex w-full items-center justify-center rounded-xl border border-sky-500 bg-sky-500/10 px-3 py-2 text-sm font-medium text-sky-200 transition hover:bg-sky-500/20"
                    >
                        Обновить
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <img
                src="{{ route('admin.ctr.svg', ['from' => $from, 'to' => $to]) }}"
                alt="CTR line chart"
                class="h-full w-full rounded-2xl border border-slate-800 bg-slate-950/70"
            />
            <img
                src="{{ route('admin.ctr.bars.svg', ['from' => $from, 'to' => $to]) }}"
                alt="CTR bars chart"
                class="h-full w-full rounded-2xl border border-slate-800 bg-slate-950/70"
            />
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-50">Итоги A/B</h3>
                <dl class="mt-4 space-y-3 text-sm text-slate-300">
                    @foreach ($summary as $row)
                        <div class="grid grid-cols-2 items-center gap-2 rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2">
                            <dt class="font-semibold text-slate-100">Вариант {{ $row['variant'] }}</dt>
                            <dd class="text-right text-sm text-slate-400">CTR {{ number_format($row['ctr'], 2) }}%</dd>
                            <dd class="col-span-2 text-xs text-slate-500">Imps: {{ number_format($row['impressions']) }} • Clicks: {{ number_format($row['clicks']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-50">Клики по площадкам</h3>
                <div class="mt-4 space-y-3 text-sm text-slate-300">
                    @forelse ($placements as $row)
                        <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2">
                            <span class="font-medium text-slate-100">{{ $row['placement'] ?? '—' }}</span>
                            <span class="text-slate-400">{{ number_format($row['clicks']) }} кликов</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Нет данных о кликах за выбранный период.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-50">Фуннели</h3>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($funnels as $funnel)
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $funnel['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-slate-50">{{ number_format($funnel['ctr'], 2) }}%</p>
                        <p class="mt-2 text-xs text-slate-500">Imps: {{ number_format($funnel['impressions']) }}</p>
                        <p class="text-xs text-slate-500">Clicks: {{ number_format($funnel['clicks']) }}</p>
                        <p class="text-xs text-slate-500">Views: {{ number_format($funnel['views']) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>

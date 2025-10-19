<x-filament-panels::page>
    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jobs</p>
                <p class="mt-3 text-3xl font-semibold text-slate-50">{{ number_format($queueCount) }}</p>
                <p class="mt-2 text-xs text-slate-500">Активные задания в очереди</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Failed</p>
                <p class="mt-3 text-3xl font-semibold text-slate-50">{{ number_format($failedCount) }}</p>
                <p class="mt-2 text-xs text-slate-500">Ошибок обработки</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Batches</p>
                <p class="mt-3 text-3xl font-semibold text-slate-50">{{ number_format($processedCount) }}</p>
                <p class="mt-2 text-xs text-slate-500">Обработанные батчи</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-semibold text-slate-50">Laravel Horizon</h3>
                <button
                    type="button"
                    wire:click="refreshMetrics"
                    class="inline-flex items-center justify-center rounded-xl border border-sky-500 bg-sky-500/10 px-4 py-2 text-sm font-medium text-sky-200 transition hover:bg-sky-500/20"
                >
                    Обновить
                </button>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Workload</p>
                    @if (!empty($horizon['workload']))
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($horizon['workload'] as $queue => $count)
                                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-3">
                                    <p class="text-sm font-medium text-slate-100">{{ $queue }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ number_format($count) }} в обработке</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-400">Данные Horizon недоступны.</p>
                    @endif
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Supervisors</p>
                    @if (!empty($horizon['supervisors']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($horizon['supervisors'] as $supervisor)
                                <span class="inline-flex items-center rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-200">{{ $supervisor }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-3 text-sm text-slate-400">Супервайзеры не подключены.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>

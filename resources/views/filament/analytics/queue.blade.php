<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-3xl border border-white/10 bg-white/10 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Очереди и Horizon</h1>
                <p class="text-sm text-white/70">Быстрая сводка по очередям Laravel и состоянию Horizon.</p>
            </div>
            <button wire:click="refreshMetrics" class="inline-flex items-center justify-center rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">
                Обновить данные
            </button>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-3xl border border-white/10 bg-slate-950/50 p-6">
                <p class="text-sm text-white/60">Отложенные задачи</p>
                <p class="mt-2 text-3xl font-semibold text-sky-400">{{ number_format($queueCount, 0, '.', ' ') }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-slate-950/50 p-6">
                <p class="text-sm text-white/60">Неудачные задачи</p>
                <p class="mt-2 text-3xl font-semibold text-rose-400">{{ number_format($failedCount, 0, '.', ' ') }}</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-slate-950/50 p-6">
                <p class="text-sm text-white/60">Завершённые партии</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-400">{{ number_format($batchCount, 0, '.', ' ') }}</p>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            <h2 class="text-lg font-semibold text-white">Horizon Workload</h2>
            @if(!empty($horizon['workload']))
                <div class="mt-4 overflow-hidden rounded-2xl border border-white/10">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm text-white">
                        <thead class="bg-white/5 text-xs uppercase text-white/60">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Очередь</th>
                                <th class="px-4 py-3 font-semibold">Задачи</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 bg-slate-950/40">
                            @foreach($horizon['workload'] as $queue => $size)
                                <tr>
                                    <td class="px-4 py-3">{{ $queue }}</td>
                                    <td class="px-4 py-3">{{ number_format((int) $size, 0, '.', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 text-sm text-white/60">Нет данных о нагрузке Horizon или Horizon неактивен.</p>
            @endif
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/10 p-6">
            <h2 class="text-lg font-semibold text-white">Supervisors</h2>
            @if(!empty($horizon['supervisors']))
                <ul class="mt-4 space-y-2">
                    @foreach($horizon['supervisors'] as $supervisor)
                        <li class="rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-sm text-white">{{ $supervisor }}</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-white/60">Активные supervisors Horizon не найдены.</p>
            @endif

            @if(isset($horizon['error']))
                <p class="mt-4 rounded-2xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ $horizon['error'] }}
                </p>
            @endif
        </div>
    </div>
</x-filament-panels::page>

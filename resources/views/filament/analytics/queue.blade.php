<x-filament-panels::page>
  <div class="space-y-6">
    <x-filament::card>
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-white">{{ __('admin.metrics.heading') }}</h3>
        <button type="button" wire:click="refreshData" class="inline-flex items-center rounded-lg border border-primary-500 px-3 py-1 text-xs font-semibold text-primary-200 hover:bg-primary-500/20 focus:outline-none focus:ring-2 focus:ring-primary-400">
          {{ __('admin.metrics.refresh') }}
        </button>
      </div>
      <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-gray-900/40 p-4">
          <dt class="text-xs uppercase tracking-wide text-gray-400">{{ __('admin.metrics.labels.jobs') }}</dt>
          <dd class="mt-2 text-2xl font-semibold text-white">{{ number_format($jobs) }}</dd>
        </div>
        <div class="rounded-lg bg-gray-900/40 p-4">
          <dt class="text-xs uppercase tracking-wide text-gray-400">{{ __('admin.metrics.labels.failed') }}</dt>
          <dd class="mt-2 text-2xl font-semibold @if($failed > 0) text-danger-400 @else text-emerald-400 @endif">{{ number_format($failed) }}</dd>
        </div>
        <div class="rounded-lg bg-gray-900/40 p-4">
          <dt class="text-xs uppercase tracking-wide text-gray-400">{{ __('admin.metrics.labels.batches') }}</dt>
          <dd class="mt-2 text-2xl font-semibold text-white">{{ number_format($batches) }}</dd>
        </div>
      </dl>
    </x-filament::card>

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.metrics.horizon.heading') }}</h3>
      @if($horizon['workload'] || $horizon['supervisors'])
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
          @if($horizon['workload'])
            <div>
              <h4 class="text-sm font-semibold text-gray-200">{{ __('admin.metrics.horizon.workload') }}</h4>
              <div class="mt-2 rounded-lg bg-gray-900/40 p-4 text-xs text-gray-200">
                <pre class="whitespace-pre-wrap break-all">{{ json_encode($horizon['workload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
              </div>
            </div>
          @endif
          @if($horizon['supervisors'])
            <div>
              <h4 class="text-sm font-semibold text-gray-200">{{ __('admin.metrics.horizon.supervisors') }}</h4>
              <ul class="mt-2 space-y-1 text-sm text-gray-300">
                @foreach($horizon['supervisors'] as $supervisor)
                  <li class="rounded bg-gray-900/40 px-3 py-2">{{ $supervisor }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      @else
        <div class="text-sm text-gray-400">{{ __('admin.metrics.horizon.empty') }}</div>
      @endif
    </x-filament::card>
  </div>
</x-filament-panels::page>

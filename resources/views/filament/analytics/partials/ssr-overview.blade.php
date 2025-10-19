@php
  /** @var array<string, array{score: float, first_byte_ms: float, samples: int, paths: int, delta?: array{score: float, first_byte_ms: float, samples: int, paths: int}, range?: array{from: string, to: string}}> $periods */
  $periods = $headline['periods'] ?? [];

  $formatDelta = static function (float $value, int $precision = 2): string {
      if (abs($value) < pow(10, -$precision) / 2) {
          return '±' . number_format(0, $precision, '.', '');
      }

      $formatted = number_format(abs($value), $precision, '.', '');
      $sign = $value > 0 ? '+' : '-';

      return $sign . $formatted;
  };
@endphp

<div class="space-y-6">
  <x-filament::card>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-white">{{ __('admin.ssr.headline.heading') }}</h2>
        <p class="mt-1 text-sm text-gray-400">{{ $headline['label'] ?? '' }}</p>
      </div>
    </div>
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
      @foreach(['today', 'yesterday', 'seven_days'] as $key)
        @php($period = $periods[$key] ?? null)
        @if($period)
          <div class="rounded-lg bg-gray-900/40 p-4">
            <p class="text-sm font-semibold text-gray-300">{{ __('admin.ssr.headline.periods.' . $key . '.label') }}</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ number_format((float) ($period['score'] ?? 0), 2) }}</p>

            @if(isset($period['delta']))
              @php($scoreDelta = (float) ($period['delta']['score'] ?? 0))
              <p class="mt-2 text-xs font-semibold uppercase tracking-wide {{ $scoreDelta > 0 ? 'text-success-400' : ($scoreDelta < 0 ? 'text-danger-400' : 'text-gray-400') }}">
                {{ __('admin.ssr.headline.deltas.score', ['value' => $formatDelta($period['delta']['score'] ?? 0)]) }}
                · {{ __('admin.ssr.headline.deltas.first_byte', ['value' => $formatDelta($period['delta']['first_byte_ms'] ?? 0, 0)]) }}
                · {{ __('admin.ssr.headline.deltas.paths', ['value' => $formatDelta($period['delta']['paths'] ?? 0, 0)]) }}
                · {{ __('admin.ssr.headline.deltas.samples', ['value' => $formatDelta($period['delta']['samples'] ?? 0, 0)]) }}
              </p>
            @endif

            @if($key === 'seven_days' && isset($period['range']))
              <p class="mt-1 text-xs text-gray-400">{{ __('admin.ssr.headline.periods.seven_days.range', ['from' => $period['range']['from'] ?? '', 'to' => $period['range']['to'] ?? '']) }}</p>
            @endif

            <dl class="mt-4 space-y-2 text-sm text-gray-300">
              <div>
                <dt class="font-medium text-gray-400">{{ __('admin.ssr.headline.metrics.first_byte') }}</dt>
                <dd>{{ number_format((float) ($period['first_byte_ms'] ?? 0), 0) }} ms</dd>
              </div>
              <div>
                <dt class="font-medium text-gray-400">{{ __('admin.ssr.headline.metrics.paths') }}</dt>
                <dd>{{ trans_choice('admin.ssr.headline.metrics.paths_count', (int) ($period['paths'] ?? 0), ['count' => number_format((int) ($period['paths'] ?? 0))]) }}</dd>
              </div>
              <div>
                <dt class="font-medium text-gray-400">{{ __('admin.ssr.headline.metrics.samples') }}</dt>
                <dd>{{ trans_choice('admin.ssr.headline.metrics.samples_count', (int) ($period['samples'] ?? 0), ['count' => number_format((int) ($period['samples'] ?? 0))]) }}</dd>
              </div>
            </dl>
          </div>
        @endif
      @endforeach
    </div>
  </x-filament::card>

  <x-filament::card>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-lg font-semibold text-white">{{ __('admin.ssr.trend.heading') }}</h3>
    </div>
    @if(empty($trend['datasets']))
      <div class="mt-4 text-sm text-gray-400">{{ __('admin.ssr.trend.empty') }}</div>
    @else
      <div class="mt-4">
        <canvas
          id="ssr-trend-chart"
          role="img"
          aria-label="{{ __('admin.ssr.trend.aria_label') }}"
          data-chart='@js($trend)'
          class="h-64 w-full"
        ></canvas>
      </div>
    @endif
  </x-filament::card>

  <x-filament::card>
    <h3 class="text-lg font-semibold text-white">{{ __('admin.ssr.drop.heading') }}</h3>
    @if(empty($drops))
      <div class="mt-2 text-sm text-gray-400">{{ __('admin.ssr.drop.empty') }}</div>
    @else
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm text-gray-100">
          <thead class="uppercase text-xs text-gray-400">
            <tr>
              <th class="px-2 py-1">{{ __('admin.ssr.drop.columns.path') }}</th>
              <th class="px-2 py-1">{{ __('admin.ssr.drop.columns.yesterday') }}</th>
              <th class="px-2 py-1">{{ __('admin.ssr.drop.columns.today') }}</th>
              <th class="px-2 py-1">{{ __('admin.ssr.drop.columns.delta') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($drops as $row)
              <tr class="border-t border-gray-800/60">
                <td class="px-2 py-1 font-medium">{{ $row['path'] }}</td>
                <td class="px-2 py-1">{{ number_format($row['score_yesterday'], 2) }}</td>
                <td class="px-2 py-1">{{ number_format($row['score_today'], 2) }}</td>
                <td class="px-2 py-1 text-danger-400">{{ number_format($row['delta'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-filament::card>
</div>

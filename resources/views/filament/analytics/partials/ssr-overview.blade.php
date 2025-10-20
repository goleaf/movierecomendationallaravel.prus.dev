<div class="space-y-6">
  <x-filament::card>
    <div class="space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-white">{{ __('admin.ssr.headline.heading') }}</h2>
          <p class="mt-1 text-sm text-gray-400">{{ $summary['description'] ?? '' }}</p>
        </div>
        @php
          $pathsCount = (int) ($summary['paths'] ?? 0);
          $samplesCount = (int) ($summary['samples'] ?? 0);
          $samplesLabel = trans_choice('admin.ssr.headline.samples', $samplesCount, ['count' => number_format($samplesCount)]);
        @endphp
        <div class="text-sm text-gray-400 text-right">
          <div>{{ __('admin.ssr.headline.source.' . ($summary['source'] ?? 'none')) }}</div>
          <div>
            {{ trans_choice('admin.ssr.headline.paths', $pathsCount, [
              'count' => number_format($pathsCount),
              'samples' => $samplesLabel,
            ]) }}
          </div>
        </div>
      </div>

      <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($summary['periods'] ?? [] as $key => $period)
          @php
            $scoreDelta = $period['score_delta'] ?? null;
            $deltaClass = $scoreDelta === null ? 'text-gray-400' : ($scoreDelta > 0 ? 'text-success-400' : ($scoreDelta < 0 ? 'text-danger-400' : 'text-gray-400'));
            $deltaText = $scoreDelta !== null
              ? __('admin.ssr.headline.metrics.delta', [
                  'delta' => ($scoreDelta > 0 ? '+' : '') . number_format((float) $scoreDelta, 2),
                  'comparison' => $period['comparison_label'] ?? '',
                ])
              : __('admin.ssr.headline.metrics.delta_unavailable');
            $firstByte = $period['first_byte_average'] ?? null;
            $firstByteDelta = $period['first_byte_delta'] ?? null;
            $firstByteClass = $firstByteDelta === null ? 'text-gray-400' : ($firstByteDelta > 0 ? 'text-danger-400' : ($firstByteDelta < 0 ? 'text-success-400' : 'text-gray-400'));
          @endphp
          <div class="rounded-lg border border-gray-800/60 bg-gray-900/40 p-4">
            <div class="flex items-center justify-between">
              <dt class="text-sm font-medium text-gray-400">
                {{ __('admin.ssr.headline.periods.' . $key . '.label', ['label' => $period['label'] ?? '']) }}
              </dt>
              <span class="text-xs font-semibold uppercase {{ $deltaClass }}">{{ $deltaText }}</span>
            </div>
            <dd class="mt-2 text-3xl font-semibold text-white">
              {{ ($period['score_average'] ?? null) !== null ? number_format((float) $period['score_average'], 2) : '—' }}
            </dd>
            <ul class="mt-3 space-y-1 text-sm text-gray-300">
              <li>{{ trans_choice('admin.ssr.headline.metrics.samples', (int) ($period['score_samples'] ?? 0), ['count' => number_format((int) ($period['score_samples'] ?? 0))]) }}</li>
              @if($firstByte !== null)
                <li>
                  {{ __('admin.ssr.headline.metrics.first_byte', ['value' => number_format((float) $firstByte, 2)]) }}
                  @if($firstByteDelta !== null)
                    <span class="{{ $firstByteClass }}">
                      {{ __('admin.ssr.headline.metrics.first_byte_delta', [
                        'delta' => ($firstByteDelta > 0 ? '+' : '') . number_format((float) $firstByteDelta, 2),
                        'comparison' => $period['comparison_label'] ?? '',
                      ]) }}
                    </span>
                  @endif
                </li>
              @else
                <li>{{ __('admin.ssr.headline.metrics.first_byte_unavailable') }}</li>
              @endif
              <li>
                {{ __('admin.ssr.headline.metrics.range', [
                  'start' => $period['range']['start'] ?? '—',
                  'end' => $period['range']['end'] ?? '—',
                ]) }}
              </li>
            </ul>
          </div>
        @endforeach
      </dl>
    </div>
  </x-filament::card>

  <x-filament::card>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-lg font-semibold text-white">{{ __('admin.ssr.trend.heading') }}</h3>
      @if(! empty($trend['labels']))
        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
          {{ trans_choice('admin.ssr.trend.range', count($trend['labels']), ['days' => count($trend['labels'])]) }}
        </span>
      @endif
    </div>
    @if(empty($trend['datasets'][0]['data'] ?? []))
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
                <td class="px-2 py-1 {{ $row['delta'] < 0 ? 'text-danger-400' : 'text-success-400' }}">{{ number_format($row['delta'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-filament::card>
</div>

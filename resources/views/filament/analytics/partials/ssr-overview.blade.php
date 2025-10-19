<div class="space-y-6">
  <x-filament::card>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-white">{{ __('admin.ssr.headline.heading') }}</h2>
        <p class="mt-1 text-sm text-gray-400">{{ $headline['description'] ?? '' }}</p>
      </div>
      <div class="text-4xl font-bold text-white">{{ number_format((float) ($headline['score'] ?? 0), 0) }}</div>
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
                <td class="px-2 py-1 text-danger-400">{{ number_format($row['delta'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </x-filament::card>
</div>

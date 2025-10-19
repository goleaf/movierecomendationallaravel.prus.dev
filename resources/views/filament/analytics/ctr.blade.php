<x-filament-panels::page>
  <div class="space-y-6">
    {{ $this->form }}

    <x-filament::card>
      <div class="text-sm text-gray-400">{{ __('admin.ctr.period', ['from' => $filters['from'], 'to' => $filters['to']]) }}</div>
      <ul class="mt-4 space-y-2">
        @forelse($summary as $row)
          <li class="text-sm text-gray-200">
            {{ __('admin.ctr.ab_summary_item', [
              'variant' => $row['variant'],
              'impressions' => number_format($row['impressions']),
              'clicks' => number_format($row['clicks']),
              'ctr' => number_format($row['ctr'], 2),
            ]) }}
          </li>
        @empty
          <li class="text-sm text-gray-400">{{ __('admin.ctr.no_data') }}</li>
        @endforelse
      </ul>
    </x-filament::card>

    <div class="grid gap-6 lg:grid-cols-2">
      <x-filament::card>
        <h3 class="text-lg font-semibold text-white">{{ __('admin.ctr.charts.daily_heading') }}</h3>
        <div class="mt-4 overflow-x-auto" @if($lineSvg) wire:ignore @endif>
          @if($lineSvg)
            <div class="min-w-full" aria-hidden="true">{!! $lineSvg !!}</div>
          @else
            <div class="text-sm text-gray-400">{{ __('admin.ctr.no_data') }}</div>
          @endif
        </div>
      </x-filament::card>
      <x-filament::card>
        <h3 class="text-lg font-semibold text-white">{{ __('admin.ctr.charts.placements_heading') }}</h3>
        <div class="mt-4 overflow-x-auto" @if($barsSvg) wire:ignore @endif>
          @if($barsSvg)
            <div class="min-w-full" aria-hidden="true">{!! $barsSvg !!}</div>
          @else
            <div class="text-sm text-gray-400">{{ __('admin.ctr.no_data') }}</div>
          @endif
        </div>
      </x-filament::card>
    </div>

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.ctr.placement_clicks.heading') }}</h3>
      @if(empty($placementClicks))
        <div class="mt-2 text-sm text-gray-400">{{ __('admin.ctr.no_data') }}</div>
      @else
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-left text-sm text-gray-200">
            <thead class="uppercase text-xs text-gray-400">
              <tr>
                <th class="px-2 py-1">{{ __('admin.ctr.placement_clicks.placement') }}</th>
                <th class="px-2 py-1">{{ __('admin.ctr.placement_clicks.clicks') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($placementClicks as $name => $value)
                <tr class="border-t border-gray-700/40">
                  <td class="px-2 py-1 font-semibold">{{ $placementOptions[$name] ?? ucfirst($name) }}</td>
                  <td class="px-2 py-1">{{ number_format($value) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-filament::card>

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.ctr.funnels.heading') }}</h3>
      <div class="text-sm text-gray-400">{{ __('admin.funnel.period', ['from' => $filters['from'], 'to' => $filters['to']]) }}</div>
      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-left text-sm text-gray-200">
          <thead class="uppercase text-xs text-gray-400">
            <tr>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.placement') }}</th>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.imps') }}</th>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.clicks') }}</th>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.views') }}</th>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.ctr') }}</th>
              <th class="px-2 py-1">{{ __('admin.funnel.headers.view_rate') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($funnels as $row)
              <tr class="border-t border-gray-700/40">
                <td class="px-2 py-1 font-semibold">{{ $row['label'] }}</td>
                <td class="px-2 py-1">{{ number_format($row['imps']) }}</td>
                <td class="px-2 py-1">{{ number_format($row['clicks']) }}</td>
                <td class="px-2 py-1">{{ number_format($row['views']) }}</td>
                <td class="px-2 py-1">{{ number_format($row['ctr'], 2) }}</td>
                <td class="px-2 py-1">{{ number_format($row['view_rate'], 2) }}</td>
              </tr>
            @empty
              <tr>
                <td class="px-2 py-2 text-sm text-gray-400" colspan="6">{{ __('admin.ctr.no_data') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </x-filament::card>
  </div>
</x-filament-panels::page>

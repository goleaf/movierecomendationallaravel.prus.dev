<x-filament-panels::page>
  <div class="space-y-6">
    {{ $this->form }}

    <x-filament::card>
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-white">{{ __('admin.experiments.snapshots.contribution_heading') }}</h3>
          <p class="mt-1 text-sm text-gray-400">{{ __('admin.experiments.snapshots.period', ['from' => $filters['from'], 'to' => $filters['to']]) }}</p>
          <div class="mt-4 overflow-x-auto" @if($contributionSvg) wire:ignore @endif>
            @if($contributionSvg)
              <div class="min-w-full" aria-hidden="true">{!! $contributionSvg !!}</div>
            @else
              <div class="text-sm text-gray-400">{{ __('admin.experiments.snapshots.empty') }}</div>
            @endif
          </div>
        </div>
      </div>
    </x-filament::card>

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.experiments.snapshots.weight_heading') }}</h3>
      <div class="mt-4 overflow-x-auto" @if($weightSvg) wire:ignore @endif>
        @if($weightSvg)
          <div class="min-w-full" aria-hidden="true">{!! $weightSvg !!}</div>
        @else
          <div class="text-sm text-gray-400">{{ __('admin.experiments.snapshots.empty') }}</div>
        @endif
      </div>
    </x-filament::card>

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.experiments.snapshots.table_heading') }}</h3>
      @if(empty($dailyRows))
        <div class="mt-2 text-sm text-gray-400">{{ __('admin.experiments.snapshots.empty') }}</div>
      @else
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-left text-sm text-gray-200">
            <thead class="uppercase text-xs text-gray-400">
              <tr>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.day') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.items') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.pop') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.recent') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.pref') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.score') }}</th>
                <th class="px-2 py-1">{{ __('admin.experiments.snapshots.columns.weights') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($dailyRows as $row)
                <tr class="border-t border-gray-700/40">
                  <td class="px-2 py-1 font-semibold">{{ $row['day'] }}</td>
                  <td class="px-2 py-1">{{ number_format($row['items']) }}</td>
                  <td class="px-2 py-1">{{ number_format($row['pop'], 3) }}</td>
                  <td class="px-2 py-1">{{ number_format($row['recent'], 3) }}</td>
                  <td class="px-2 py-1">{{ number_format($row['pref'], 3) }}</td>
                  <td class="px-2 py-1">{{ number_format($row['score'], 3) }}</td>
                  <td class="px-2 py-1 text-xs text-gray-300">
                    {{ number_format($row['weights']['pop'], 3) }} /
                    {{ number_format($row['weights']['recent'], 3) }} /
                    {{ number_format($row['weights']['pref'], 3) }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-filament::card>
  </div>
</x-filament-panels::page>

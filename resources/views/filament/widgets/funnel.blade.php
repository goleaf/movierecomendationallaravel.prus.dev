<x-filament::card>
  <div class="text-sm text-gray-400">{{ __('analytics.widgets.funnel.period', ['from' => $from, 'to' => $to]) }}</div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left text-gray-200" style="margin-top:12px;">
      <thead class="text-xs uppercase text-gray-400">
        <tr>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.placement') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.imps') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.clicks') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.views') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.ctr') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.cuped_ctr') }}</th>
          <th class="px-2 py-1">{{ __('analytics.widgets.funnel.columns.view_rate') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr class="border-t border-gray-700/40">
            <td class="px-2 py-1 font-semibold">{{ $row['label'] }}</td>
            <td class="px-2 py-1">{{ number_format($row['imps']) }}</td>
            <td class="px-2 py-1">{{ number_format($row['clicks']) }}</td>
            <td class="px-2 py-1">{{ number_format($row['views']) }}</td>
            <td class="px-2 py-1">{{ number_format($row['ctr'], 2) }}</td>
            <td class="px-2 py-1">{{ number_format($row['cuped_ctr'], 2) }}</td>
            <td class="px-2 py-1">{{ number_format($row['view_rate'], 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-filament::card>

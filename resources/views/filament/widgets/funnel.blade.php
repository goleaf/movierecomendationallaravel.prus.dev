<x-filament::card>
  <div class="text-sm text-gray-400">Период: {{ $from }} — {{ $to }}</div>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left text-gray-200" style="margin-top:12px;">
      <thead class="text-xs uppercase text-gray-400">
        <tr>
          <th class="px-2 py-1">Placement</th>
          <th class="px-2 py-1">Imps</th>
          <th class="px-2 py-1">Clicks</th>
          <th class="px-2 py-1">Views</th>
          <th class="px-2 py-1">CTR %</th>
          <th class="px-2 py-1">View→Click %</th>
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
            <td class="px-2 py-1">{{ number_format($row['view_rate'], 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</x-filament::card>

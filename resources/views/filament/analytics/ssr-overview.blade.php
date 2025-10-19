@php
    $paths = (int) ($summary['path_count'] ?? 0);
    $score = (int) ($summary['score'] ?? 0);
    $capturedAt = $summary['captured_at'] ?? null;
    $rows = [];

    foreach ($trend['labels'] as $i => $label) {
        $rows[] = [
            'label' => $label,
            'value' => $trend['values'][$i] ?? null,
        ];
    }

    if (count($rows) > 7) {
        $rows = array_slice($rows, -7);
    }
@endphp

<div class="muted" style="display:grid;gap:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:18px">
        <div>
            <div style="font-size:2.5rem;font-weight:700;color:#f8fafc">{{ $score }}</div>
            <div>{{ trans_choice('analytics.widgets.ssr_stats.description', $paths, ['count' => number_format($paths)]) }}</div>
        </div>
        <div style="text-align:right">
            <div>{{ __('analytics.widgets.ssr_score.dataset') }}</div>
            <div style="font-size:0.85rem;color:#94a3b8">
                @if($capturedAt instanceof \Illuminate\Support\Carbon)
                    {{ $capturedAt->toDayDateTimeString() }}
                @else
                    {{ __('analytics.widgets.ssr_drop.columns.today') }}
                @endif
            </div>
        </div>
    </div>

    <div>
        <table style="width:100%;border-collapse:collapse;font-size:0.9rem">
            <thead>
                <tr style="text-align:left;color:#94a3b8">
                    <th style="padding:6px 0">{{ __('analytics.widgets.ssr_score.date_column') }}</th>
                    <th style="padding:6px 0;text-align:right">{{ __('analytics.widgets.ssr_score.dataset') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="padding:6px 0">{{ $row['label'] }}</td>
                        <td style="padding:6px 0;text-align:right;font-variant-numeric:tabular-nums">
                            {{ $row['value'] !== null ? number_format((float) $row['value'], 2) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" style="padding:6px 0;color:#94a3b8">{{ __('analytics.widgets.ssr_drop.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

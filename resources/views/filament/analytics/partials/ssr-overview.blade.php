@php
  /** @var array<string, float|int|null> $summary */
  /** @var array{points: array<int, array{date: string, score: float}>} $trend */
  /** @var array<int, array{path: string, today: float, yesterday: float, delta: float}> $drops */
  /** @var \Carbon\CarbonInterface|null $lastUpdated */
  $summary = $summary ?? [];
  $trendPoints = $trend['points'] ?? [];
  $drops = $drops ?? [];
  $source = $source ?? 'database';
  $formatMetric = static function ($value, string $key) {
      if ($value === null) {
          return __('messages.common.dash');
      }

      return match ($key) {
          'path_count' => number_format((int) $value),
          'avg_html_size' => number_format((float) $value, 1) . ' KB',
          'avg_first_byte_ms' => number_format((float) $value, 0) . ' ms',
          default => number_format((float) $value, 2),
      };
  };

  $metricsOrder = [
      'average_score' => __('admin.ssr.summary.metrics.average_score'),
      'path_count' => __('admin.ssr.summary.metrics.path_count'),
      'avg_html_size' => __('admin.ssr.summary.metrics.avg_html_size'),
      'avg_meta_tags' => __('admin.ssr.summary.metrics.avg_meta_tags'),
      'avg_og_tags' => __('admin.ssr.summary.metrics.avg_og_tags'),
      'avg_ldjson_blocks' => __('admin.ssr.summary.metrics.avg_ldjson_blocks'),
      'avg_blocking_scripts' => __('admin.ssr.summary.metrics.avg_blocking_scripts'),
      'avg_first_byte_ms' => __('admin.ssr.summary.metrics.avg_first_byte_ms'),
  ];
@endphp

<div class="ssr-overview" style="display: flex; flex-direction: column; gap: 16px;">
  @if($source === 'jsonl')
    <div class="card">
      <h3>{{ __('admin.ssr.fallback.heading') }}</h3>
      <p class="muted">{{ __('admin.ssr.fallback.description') }}</p>
    </div>
  @elseif($source === 'empty')
    <div class="card">
      <p class="muted">{{ __('admin.ssr.empty') }}</p>
    </div>
  @endif

  <div class="card">
    <h3>{{ __('admin.ssr.summary.heading') }}</h3>
    @if(!empty($lastUpdated))
      <p class="muted">{{ __('admin.ssr.summary.last_updated', ['timestamp' => $lastUpdated->setTimezone(config('app.timezone'))->format('Y-m-d H:i')]) }}</p>
    @endif
    <ul style="list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 8px;">
      @foreach($metricsOrder as $key => $label)
        @if(array_key_exists($key, $summary))
          <li>
            <strong>{{ $label }}:</strong>
            <span class="muted" style="margin-left: 4px;">{{ $formatMetric($summary[$key], $key) }}</span>
          </li>
        @endif
      @endforeach
    </ul>
  </div>

  <div class="card">
    <h3>{{ __('admin.ssr.trend.heading') }}</h3>
    <p class="muted">{{ __('admin.ssr.trend.description', ['days' => \App\Services\Analytics\SsrDashboardService::TREND_LOOKBACK_DAYS]) }}</p>
    @if(empty($trendPoints))
      <p class="muted">{{ __('admin.ssr.trend.empty') }}</p>
    @else
      <div style="overflow-x: auto; margin-top: 12px;">
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.trend.columns.date') }}</th>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.trend.columns.score') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($trendPoints as $point)
              <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ $point['date'] }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ number_format($point['score'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card">
    <h3>{{ __('admin.ssr.drops.heading') }}</h3>
    <p class="muted">{{ __('admin.ssr.drops.description') }}</p>
    @if(empty($drops))
      <p class="muted">{{ __('admin.ssr.drops.empty') }}</p>
    @else
      <div style="overflow-x: auto; margin-top: 12px;">
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.drops.columns.path') }}</th>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.drops.columns.yesterday') }}</th>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.drops.columns.today') }}</th>
              <th style="text-align: left; padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ __('admin.ssr.drops.columns.delta') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($drops as $row)
              <tr>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ $row['path'] }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ number_format($row['yesterday'], 2) }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933;">{{ number_format($row['today'], 2) }}</td>
                <td style="padding: 6px 8px; border-bottom: 1px solid #1f2933; color: {{ $row['delta'] < 0 ? '#f87171' : '#34d399' }};">{{ number_format($row['delta'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

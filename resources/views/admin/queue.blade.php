@extends('layouts.app')

@section('title', __('admin.queue_dashboard.title'))

@section('content')
<div class="card">
  <h3>{{ __('admin.queue_dashboard.heading') }}</h3>
  <p class="muted">{{ __('admin.queue_dashboard.description') }}</p>

  <div class="grid grid-4">
    @foreach(['ingestion', 'recommendations', 'other'] as $pipeline)
      @php($data = $pipelines[$pipeline] ?? ['jobs' => 0, 'failed' => 0, 'queues' => []])
      <div class="card">
        <h4>{{ __('admin.queue_dashboard.pipelines.' . $pipeline . '.label') }}</h4>
        <p><strong>{{ __('admin.queue_dashboard.labels.jobs') }}:</strong> {{ number_format($data['jobs']) }}</p>
        <p><strong>{{ __('admin.queue_dashboard.labels.failed') }}:</strong> {{ number_format($data['failed']) }}</p>
        @if($pipeline === 'other')
          <p class="muted">
            @if($data['queues'])
              {{ __('admin.queue_dashboard.pipelines.other.queues', ['queues' => implode(', ', $data['queues'])]) }}
            @else
              {{ __('admin.queue_dashboard.pipelines.other.empty') }}
            @endif
          </p>
        @else
          <p class="muted">{{ __('admin.queue_dashboard.pipelines.' . $pipeline . '.queues', ['queues' => implode(', ', $data['queues'])]) }}</p>
        @endif
      </div>
    @endforeach
  </div>

  <p class="muted">{{ __('admin.queue_dashboard.totals', ['jobs' => number_format($totals['jobs']), 'failed' => number_format($totals['failed'])]) }}</p>
</div>

<div class="card">
  <h3>{{ __('admin.queue_dashboard.details.heading') }}</h3>
  @if($uncategorized)
    <table class="muted" style="width:100%; border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left; padding:4px;">{{ __('admin.queue_dashboard.details.queue') }}</th>
          <th style="text-align:right; padding:4px;">{{ __('admin.queue_dashboard.labels.jobs') }}</th>
          <th style="text-align:right; padding:4px;">{{ __('admin.queue_dashboard.labels.failed') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($uncategorized as $queueName => $queue)
          <tr>
            <td style="padding:4px;">{{ $queueName }}</td>
            <td style="padding:4px; text-align:right;">{{ number_format($queue['jobs']) }}</td>
            <td style="padding:4px; text-align:right;">{{ number_format($queue['failed']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="muted">{{ __('admin.queue_dashboard.details.empty') }}</p>
  @endif
</div>
@endsection

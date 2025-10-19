@extends('layouts.app')

@php
    $points = $timeline['points'];
    $totalJobs = array_sum(array_map(static fn (array $point): int => (int) $point['jobs'], $points));
    $totalFailures = array_sum(array_map(static fn (array $point): int => (int) $point['failures'], $points));
    $hasActivity = $totalJobs > 0 || $totalFailures > 0;
    $chartWidth = 720;
    $chartHeight = 200;

    $buildPolyline = static function (array $pointsData, string $metric) use ($chartWidth, $chartHeight): array {
        if ($pointsData === []) {
            return [
                'points' => '',
                'max' => 0,
            ];
        }

        $values = array_map(static fn (array $point): int => (int) $point[$metric], $pointsData);
        $maxValue = max($values);
        if ($maxValue <= 0) {
            $maxValue = 1;
        }

        $count = count($pointsData);
        $segments = [];

        foreach ($pointsData as $index => $point) {
            $x = $count === 1 ? $chartWidth / 2.0 : ($index / ($count - 1)) * $chartWidth;
            $y = $chartHeight - (($point[$metric] / $maxValue) * $chartHeight);
            $segments[] = sprintf('%.2f,%.2f', $x, $y);
        }

        return [
            'points' => implode(' ', $segments),
            'max' => $maxValue,
        ];
    };

    $jobsChart = $buildPolyline($points, 'jobs');
    $failuresChart = $buildPolyline($points, 'failures');

    $from = \Carbon\CarbonImmutable::parse($timeline['from'])->timezone(config('app.timezone'));
    $to = \Carbon\CarbonImmutable::parse($timeline['to'])->timezone(config('app.timezone'));
@endphp

@section('title', __('admin.queues.title'))

@section('content')
    <div class="card" style="margin-bottom: 16px;">
        <h2>{{ __('admin.queues.heading', ['minutes' => $timeline['interval_minutes']]) }}</h2>
        <p class="muted">{{ __('admin.queues.window', ['from' => $from->format('Y-m-d H:i'), 'to' => $to->format('Y-m-d H:i'), 'timezone' => config('app.timezone')]) }}</p>
        <p class="muted">{{ __('admin.queues.summary', ['jobs' => $totalJobs, 'failures' => $totalFailures]) }}</p>
        <p><a href="{{ route('admin.queues.export') }}">{{ __('admin.queues.export_csv') }}</a></p>
    </div>

    @if (! $hasActivity)
        <div class="card">
            <p class="muted">{{ __('admin.queues.empty') }}</p>
        </div>
    @else
        <div class="grid" style="gap: 16px;">
            <div class="card">
                <h3>{{ __('admin.queues.jobs_chart') }}</h3>
                <p class="muted">{{ __('admin.queues.chart_max', ['value' => $jobsChart['max']]) }}</p>
                <svg role="img" aria-label="{{ __('admin.queues.jobs_chart_aria') }}" width="100%" height="240" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight + 40 }}">
                    <line x1="0" y1="{{ $chartHeight + 20 }}" x2="{{ $chartWidth }}" y2="{{ $chartHeight + 20 }}" stroke="#1f2937" stroke-width="1" />
                    <polyline fill="none" stroke="#38bdf8" stroke-width="3" points="{{ $jobsChart['points'] }}" transform="translate(0,20)" />
                </svg>
            </div>
            <div class="card">
                <h3>{{ __('admin.queues.failures_chart') }}</h3>
                <p class="muted">{{ __('admin.queues.chart_max', ['value' => $failuresChart['max']]) }}</p>
                <svg role="img" aria-label="{{ __('admin.queues.failures_chart_aria') }}" width="100%" height="240" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight + 40 }}">
                    <line x1="0" y1="{{ $chartHeight + 20 }}" x2="{{ $chartWidth }}" y2="{{ $chartHeight + 20 }}" stroke="#1f2937" stroke-width="1" />
                    <polyline fill="none" stroke="#f87171" stroke-width="3" points="{{ $failuresChart['points'] }}" transform="translate(0,20)" />
                </svg>
            </div>
        </div>
    @endif
@endsection

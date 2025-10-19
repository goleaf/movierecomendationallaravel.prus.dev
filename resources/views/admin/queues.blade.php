@extends('layouts.app')
@section('title', __('admin.queues.title'))
@section('content')
<div class="card">
    <h3>{{ __('admin.queues.heading') }}</h3>
    <p>{{ __('admin.queues.description') }}</p>
    <p><a class="button" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">{{ __('admin.queues.download_csv') }}</a></p>

    <table class="table">
        <thead>
            <tr>
                <th>{{ __('admin.queues.table.queue') }}</th>
                <th>{{ __('admin.queues.table.in_flight') }}</th>
                <th>{{ __('admin.queues.table.failures') }}</th>
                <th>{{ __('admin.queues.table.avg_runtime') }}</th>
                <th>{{ __('admin.queues.table.jobs_per_minute') }}</th>
                <th>{{ __('admin.queues.table.processed') }}</th>
                <th>{{ __('admin.queues.table.batches') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($metrics['queues'] as $queue)
                <tr>
                    <td>{{ $queue['queue'] }}</td>
                    <td>{{ number_format($queue['in_flight']) }}</td>
                    <td>{{ number_format($queue['failed']) }}</td>
                    <td>{{ number_format($queue['average_runtime_seconds'], 2) }}s</td>
                    <td>{{ number_format($queue['jobs_per_minute'], 2) }}</td>
                    <td>{{ number_format($queue['processed_jobs']) }}</td>
                    <td>{{ number_format($queue['batches']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('admin.queues.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th>{{ __('admin.queues.table.total') }}</th>
                <th>{{ number_format($metrics['totals']['in_flight']) }}</th>
                <th>{{ number_format($metrics['totals']['failed']) }}</th>
                <th>{{ number_format($metrics['totals']['average_runtime_seconds'], 2) }}s</th>
                <th>{{ number_format($metrics['totals']['jobs_per_minute'], 2) }}</th>
                <th>{{ number_format($metrics['totals']['processed_jobs']) }}</th>
                <th>{{ number_format($metrics['totals']['batches']) }}</th>
            </tr>
        </tfoot>
    </table>

    <p class="muted">{{ __('admin.queues.generated_at', ['timestamp' => $metrics['generated_at']->format('Y-m-d H:i:s')]) }}</p>
</div>
@endsection

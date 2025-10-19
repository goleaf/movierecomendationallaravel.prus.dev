@extends('layouts.app')
@section('title', __('admin.metrics.title'))
@section('content')
<div class="card"><h3>{{ __('admin.metrics.heading') }}</h3>
<p>{{ __('admin.metrics.stats', [
    'jobs' => $queueCount,
    'failed' => $failed,
    'batches' => $processed,
]) }}</p>
@if($horizon['workload'])
  <pre class="muted">{{ json_encode($horizon['workload']) }}</pre>
@endif
</div>
@endsection

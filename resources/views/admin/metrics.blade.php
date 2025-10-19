@extends('layouts.app')
@section('title','Очереди / Horizon')
@section('content')
<div class="card"><h3>Очереди</h3>
<p>jobs: {{ $queueCount }}, failed: {{ $failed }}, batches: {{ $processed }}</p>
@if($horizon['workload'])
  <pre class="muted">{{ json_encode($horizon['workload']) }}</pre>
@endif
</div>
@endsection

@extends('layouts.app')

@section('title', __('admin.ssr.title'))

@section('content')
<div class="card" style="margin-bottom: 16px;">
  <h2>{{ __('admin.ssr.heading') }}</h2>
  <p class="muted">{{ __('admin.ssr.description') }}</p>
</div>

@include('filament.analytics.partials.ssr-overview', [
    'summary' => $summary,
    'trend' => $trend,
    'drops' => $drops,
    'source' => $source,
    'lastUpdated' => $lastUpdated,
])
@endsection

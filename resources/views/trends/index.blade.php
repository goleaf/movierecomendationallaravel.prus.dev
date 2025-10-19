@extends('layouts.app')
@section('title', __('messages.trends.title'))
@section('content')
@php
    $typeLabel = match ($type) {
        'movie' => __('messages.search.form.type_movie'),
        'series' => __('messages.search.form.type_series'),
        'animation' => __('messages.search.form.type_animation'),
        default => __('messages.trends.filters.any'),
    };

    $genreLabel = $genre !== '' ? $genre : __('messages.trends.filters.any');
    $yearFromLabel = $yf > 0 ? $yf : __('messages.trends.filters.any');
    $yearToLabel = $yt > 0 ? $yt : __('messages.trends.filters.any');
@endphp

<div class="card" style="margin-bottom:16px;">
  <h2>{{ __('messages.trends.heading') }}</h2>
  <p class="muted">{{ __('messages.trends.period', ['from' => $from, 'to' => $to, 'days' => $days, 'days_short' => __('messages.trends.days_short')]) }}</p>
  <dl class="grid grid-2" style="gap:8px; margin-top:12px;">
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.days') }}</dt>
      <dd>{{ $days }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.type') }}</dt>
      <dd>{{ $typeLabel }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.genre') }}</dt>
      <dd>{{ $genreLabel }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.year_from') }}</dt>
      <dd>{{ $yearFromLabel }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.year_to') }}</dt>
      <dd>{{ $yearToLabel }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.from') }}</dt>
      <dd>{{ $from }}</dd>
    </div>
    <div>
      <dt class="muted" style="margin-bottom:4px;">{{ __('messages.trends.filters.to') }}</dt>
      <dd>{{ $to }}</dd>
    </div>
  </dl>
</div>
@if(collect($items)->isEmpty())
  <div class="muted">{{ __('messages.trends.empty') }}</div>
@else
  <div class="grid grid-4">
    @foreach($items as $item)
      <a class="card" href="{{ route('movies.show', ['movie'=>$item->id, 'placement'=>$item->placement ?? 'trends', 'variant'=>$item->variant ?? 'mixed']) }}">
        @if($item->poster_url)
          <img src="{{ $item->poster_url }}" alt="{{ $item->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $item->title }}</strong> ({{ $item->year ?? __('messages.common.dash') }})</div>
        <div class="muted">
          {{ __('messages.common.clicks', ['count' => $item->clicks ?? __('messages.common.dash')]) }}
          @if($item->imdb_rating)
            • {{ __('messages.common.imdb_only', ['rating' => $item->imdb_rating]) }}
          @endif
          @if($item->imdb_votes)
            • {{ __('messages.trends.votes', ['count' => number_format($item->imdb_votes, 0, '.', ' ')]) }}
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection

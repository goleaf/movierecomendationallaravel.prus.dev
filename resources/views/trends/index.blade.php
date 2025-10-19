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
  <p class="muted">{{ __('messages.trends.period', ['from' => $period['from'], 'to' => $period['to'], 'days' => $period['days'], 'days_short' => __('messages.trends.days_short')]) }}</p>
</div>
@if(collect($items)->isEmpty())
  <div class="muted">{{ __('messages.trends.empty') }}</div>
@else
  <div class="grid grid-4">
    @foreach($items as $item)
      @php($poster = proxy_image_url($item->poster_url))
      <a class="card" href="{{ route('movies.show', ['movie'=>$item->id, 'placement'=>$item->placement ?? 'trends', 'variant'=>$item->variant ?? 'mixed']) }}">
        @if($poster)
          <img src="{{ $poster }}" alt="{{ $item->title ? 'Постер фильма «' . $item->title . '»' : 'Постер фильма' }}" loading="lazy"/>
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

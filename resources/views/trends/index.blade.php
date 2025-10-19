@extends('layouts.app')
@section('title', __('messages.trends.title'))
@section('content')
<div class="card" style="margin-bottom:16px;">
  <h2>{{ __('messages.trends.heading') }}</h2>
  <p class="muted">{{ __('messages.trends.period', ['from' => $from, 'to' => $to, 'days' => $days, 'days_short' => __('messages.trends.days_short')]) }}</p>
</div>
@if(collect($items)->isEmpty())
  <div class="muted">{{ __('messages.trends.empty') }}</div>
@else
  <div class="grid grid-4">
    @foreach($items as $item)
      <a class="card" href="{{ route('movies.show', ['movie'=>$item->id]) }}">
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

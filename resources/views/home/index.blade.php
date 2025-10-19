@extends('layouts.app')
@section('title', __('messages.home.title'))
@section('content')
<div class="card" style="margin-bottom:16px;">
  <h2>{{ __('messages.home.recommendations_heading') }}</h2>
  <p class="muted">{{ __('messages.home.recommendations_description') }}</p>
</div>
@if($recommended->isEmpty())
  <div class="muted">{{ __('messages.home.empty_recommendations') }}</div>
@else
  <div class="grid grid-4">
    @foreach($recommended as $movie)
      <a class="card" href="{{ route('movies.show', $movie) }}">
        @if($movie->poster_url)
          <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? __('messages.common.dash') }})</div>
        <div class="muted">{{ __('messages.common.imdb_with_votes_colon', ['rating' => $movie->imdb_rating ?? __('messages.common.dash'), 'votes' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]) }}</div>
      </a>
    @endforeach
  </div>
@endif

<div class="card" style="margin-top:24px;margin-bottom:12px;">
  <h3>{{ __('messages.home.trends_heading') }}</h3>
  <p class="muted">{!! __('messages.home.trends_description_html', ['url' => route('trends')]) !!}</p>
</div>
@if($trending->isEmpty())
  <div class="muted">{{ __('messages.home.empty_trending') }}</div>
@else
  <div class="grid grid-4">
    @foreach($trending as $row)
      @php($movie = $row['movie'])
      <a class="card" href="{{ route('movies.show', $movie) }}">
        @if($movie->poster_url)
          <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? __('messages.common.dash') }})</div>
        <div class="muted">
          @if(!is_null($row['clicks']))
            {{ __('messages.common.clicks', ['count' => $row['clicks']]) }}
            @if($movie->imdb_rating)
              • {{ __('messages.common.imdb_only', ['rating' => $movie->imdb_rating]) }}
            @endif
          @else
            {{ __('messages.common.imdb_with_votes', ['rating' => $movie->imdb_rating ?? __('messages.common.dash'), 'votes' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]) }}
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection

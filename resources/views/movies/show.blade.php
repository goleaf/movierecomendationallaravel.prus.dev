@extends('layouts.app')
@section('title', $movie->title)
@section('content')
<div class="card">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
    @if($movie->poster_url)<img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}"/><br>@endif
    <div>
      <h2>{{ $movie->title }} ({{ $movie->year ?? __('messages.common.dash') }})</h2>
      <div class="muted">{{ __('messages.movies.imdb_caption', [
        'rating' => $movie->imdb_rating ?? __('messages.common.dash'),
        'votes' => __('messages.movies.votes', ['count' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]),
        'score' => $movie->weighted_score,
      ]) }}</div>
      <p>{{ $movie->plot }}</p>
      @if($movie->genres)<div class="muted">{{ __('messages.movies.genres', ['genres' => implode(', ', $movie->genres)]) }}</div>@endif
    </div>
</div>
@endsection

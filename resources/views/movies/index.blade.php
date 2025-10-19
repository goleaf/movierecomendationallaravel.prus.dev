@extends('layouts.app')

@section('title', 'Movies')

@section('content')
<div class="card" style="margin-bottom:16px;">
  <h2>Movies</h2>
</div>
@if($movies->isEmpty())
  <div class="muted">No movies found.</div>
@else
  <div class="grid grid-4">
    @foreach($movies as $movie)
      <a class="card" href="{{ route('movies.show', ['movie' => $movie, 'placement' => 'index', 'variant' => 'index']) }}">
        @if($movie->poster_url)
          <img src="{{ $movie->poster_url }}" alt="{{ $movie->title ? __('messages.common.poster_alt', ['title' => $movie->title]) : __('messages.common.poster_alt_generic') }}" loading="lazy"/>
        @endif
        <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? __('messages.common.dash') }})</div>
        <div class="muted">{{ __('messages.common.imdb_with_votes_colon', ['rating' => $movie->imdb_rating ?? __('messages.common.dash'), 'votes' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]) }}</div>
      </a>
    @endforeach
  </div>
  <div style="margin-top:16px;">
    {{ $movies->withQueryString()->links() }}
  </div>
@endif
@endsection

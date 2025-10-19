@extends('layouts.app')
@section('title', $movie->title)
@section('content')
<div class="card">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
    @if($movie->poster_url)
      @php($posterSrcset = poster_srcset($movie->poster_url))
      <img
        src="{{ $movie->poster_url }}"
        @if ($posterSrcset)
          srcset="{{ e($posterSrcset) }}"
          sizes="220px"
        @endif
        alt="{{ $movie->title ? 'Постер фильма «' . $movie->title . '»' : 'Постер фильма' }}"
        loading="lazy"
        decoding="async"
      />
    @endif
    <div>
      <h2>{{ $movie->title }} ({{ $movie->year ?? __('messages.common.dash') }})</h2>
      <div class="muted">{{ __('messages.movies.imdb_caption', [
        'rating' => $movie->imdb_rating ?? __('messages.common.dash'),
        'votes' => __('messages.movies.votes', ['count' => number_format($movie->imdb_votes ?? 0, 0, '.', ' ')]),
        'score' => $movie->weighted_score,
      ]) }}</div>
      <p>{{ $movie->plot }}</p>
      @if($movie->genres)
        <div class="muted">{{ __('messages.movies.genres', ['genres' => implode(', ', $movie->genres)]) }}</div>
      @endif
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
    <div>
      <h3 style="margin:0;">Discussion</h3>
      <div class="muted">{{ number_format($movie->comments_count ?? 0) }} {{ \Illuminate\Support\Str::plural('comment', $movie->comments_count ?? 0) }}</div>
    </div>
  </div>
  <div style="margin-top:12px;">
    @livewire('commentions::comments', [
      'record' => $movie,
      'mentionables' => $commentMentionables,
    ], key('movie-comments-' . $movie->getKey()))
  </div>
</div>
@endsection

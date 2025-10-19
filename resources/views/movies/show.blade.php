@extends('layouts.app')
@section('title', $movie->title)
@section('canonical', route('movies.show', $movie))
@section('og_image', $movie->poster_url ?: asset('images/og-default.svg'))
@if($movie->plot)
  @section('meta_description', \Illuminate\Support\Str::limit($movie->plot, 155))
@endif
@section('structured_data')
@php
    $movieStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Movie',
        'name' => $movie->title,
        'image' => $movie->poster_url ?: asset('images/og-default.svg'),
        'description' => $movie->plot,
        'genre' => $movie->genres,
        'datePublished' => optional($movie->release_date)?->format('Y-m-d'),
        'url' => route('movies.show', $movie),
    ];

    if ($movie->imdb_rating && $movie->imdb_votes) {
        $movieStructuredData['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $movie->imdb_rating,
            'ratingCount' => $movie->imdb_votes,
        ];
    }

    if ($movie->runtime_min) {
        $movieStructuredData['duration'] = sprintf('PT%dM', $movie->runtime_min);
    }

    $movieStructuredData = array_filter(
        $movieStructuredData,
        static fn ($value) => $value !== null && $value !== []
    );
@endphp
<script nonce="{{ csp_nonce() }}" type="application/ld+json">{!! json_encode($movieStructuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endsection

@section('content')
<div class="card">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
    <img src="{{ $movie->poster_url ?: asset('images/og-default.svg') }}" alt="{{ $movie->title ? 'Постер фильма «' . $movie->title . '»' : 'Постер фильма' }}"/>
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

@extends('layouts.app')

@php
    $posterImage = artwork_url($movie->poster_url) ?? asset('img/og-default.svg');
    $canonicalUrl = route('movies.show', $movie);
    $descriptionSource = $movie->plot ?? '';
    $metaDescription = $descriptionSource !== ''
        ? \Illuminate\Support\Str::limit($descriptionSource, 160, '…')
        : __('messages.app.meta_description');
    $ogDescription = $descriptionSource !== ''
        ? \Illuminate\Support\Str::limit($descriptionSource, 200, '…')
        : __('messages.app.og_description');
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Movie',
        'name' => $movie->title,
        'url' => $canonicalUrl,
        'image' => $posterImage,
    ];

    if ($descriptionSource !== '') {
        $structuredData['description'] = $movie->plot;
    }

    if ($movie->genres) {
        $structuredData['genre'] = $movie->genres;
    }

    if ($movie->runtime_min) {
        $structuredData['duration'] = 'PT'.(int) $movie->runtime_min.'M';
    }

    if ($movie->release_date) {
        $structuredData['datePublished'] = $movie->release_date->toDateString();
    } elseif ($movie->year) {
        $structuredData['datePublished'] = (string) $movie->year;
    }

    if ($movie->imdb_rating && $movie->imdb_votes) {
        $structuredData['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $movie->imdb_rating,
            'ratingCount' => (int) $movie->imdb_votes,
        ];
    }
@endphp

@section('title', $movie->title)
@section('canonical_url', $canonicalUrl)
@section('meta_description', $metaDescription)
@section('og_title', $movie->title.($movie->year ? ' ('.$movie->year.')' : ''))
@section('og_desc', $ogDescription)
@section('og_image', $posterImage)

@push('structured_data')
    <script nonce="{{ csp_nonce() }}" type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endpush

@section('content')
<div class="card">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
    <img src="{{ $posterImage }}" alt="{{ $movie->title ? 'Постер фильма «' . $movie->title . '»' : 'Постер фильма' }}" loading="lazy"/>
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

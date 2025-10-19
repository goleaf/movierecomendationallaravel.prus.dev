@extends('layouts.app')
@section('title', $movie->title)
@section('content')
<div class="card">
  <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
    @if($movie->poster_url)<img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}"/><br>@endif
    <div>
      <h2>{{ $movie->title }} ({{ $movie->year ?? '—' }})</h2>
      <div class="muted">IMDb {{ $movie->imdb_rating ?? '—' }} • {{ $movie->imdb_votes ?? 0 }} голосов • Weighted {{ $movie->weighted_score }}</div>
      <p>{{ $movie->plot }}</p>
      @if($movie->genres)<div class="muted">Жанры: {{ implode(', ', $movie->genres) }}</div>@endif
    </div>
  </div>
</div>
@endsection

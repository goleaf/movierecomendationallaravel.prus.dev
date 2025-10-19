@extends('layouts.app')

@section('title', 'Movies')

@section('content')
  <div class="card" style="margin-bottom:20px;">
    <form method="get" class="movies-filter" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;">
      <input type="search" name="q" value="{{ $query->get('q', '') }}" placeholder="Search by title or IMDB id">
      <input type="text" name="type" value="{{ $query->get('type', '') }}" placeholder="Type (movie, series, animation)">
      @php($genres = $query->values('genres'))
      @foreach($genres as $genre)
        <input type="text" name="genres[]" value="{{ $genre }}" placeholder="Genre">
      @endforeach
      <input type="text" name="genres[]" value="" placeholder="Genre">
      <input type="number" name="year_from" value="{{ $query->get('year_from') }}" placeholder="Year from">
      <input type="number" name="year_to" value="{{ $query->get('year_to') }}" placeholder="Year to">
      <input type="hidden" name="sort" value="{{ $currentSort }}">
      <button type="submit">Apply filters</button>
    </form>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div class="muted" style="margin-bottom:8px;">Sort by</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
      @foreach($sortOptions as $value => $label)
        @php($sortQuery = $query->merge(['sort' => $value]))
        <a
          href="{{ route('movies.index', $sortQuery) }}"
          data-testid="sort-option-{{ $value }}"
          @class(['badge', 'badge-primary' => $currentSort === $value, 'badge-muted' => $currentSort !== $value])
        >
          {{ $label }}
        </a>
      @endforeach
    </div>
  </div>

  <h2 style="margin-bottom:10px;">Movies</h2>
  @if($movies->isEmpty())
    <div class="muted">No movies found for the selected filters.</div>
  @else
    <div class="grid grid-4" style="margin-bottom:20px;">
      @foreach($movies as $movie)
        <a class="card" href="{{ route('movies.show', ['movie' => $movie, 'placement' => 'catalog', 'variant' => 'list']) }}">
          @if($movie->poster_url)
            <img src="{{ $movie->poster_url }}" alt="Poster for {{ $movie->title }}">
          @endif
          <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? '—' }})</div>
          <div class="muted">IMDB: {{ $movie->imdb_rating ?? '—' }} ({{ number_format($movie->imdb_votes ?? 0) }} votes)</div>
        </a>
      @endforeach
    </div>
  @endif

  @if($movies->hasPages())
    <div class="pagination" style="margin-bottom:30px;" data-testid="movies-pagination" data-prev="{{ $movies->previousPageUrl() }}" data-next="{{ $movies->nextPageUrl() }}">
      <div style="display:flex;gap:10px;">
        @if($movies->onFirstPage())
          <span class="muted">Previous</span>
        @else
          <a href="{{ $movies->previousPageUrl() }}">Previous</a>
        @endif
        <span>Page {{ $movies->currentPage() }} of {{ $movies->lastPage() }}</span>
        @if($movies->hasMorePages())
          <a href="{{ $movies->nextPageUrl() }}">Next</a>
        @else
          <span class="muted">Next</span>
        @endif
      </div>
    </div>
  @endif

  <h2 style="margin-bottom:10px;">Recommendations</h2>
  @if($recommendations->isEmpty())
    <div class="muted">No recommendations yet.</div>
  @else
    <div class="grid grid-4" style="margin-bottom:20px;">
      @foreach($recommendations as $movie)
        <a class="card" href="{{ route('movies.show', ['movie' => $movie, 'placement' => 'recommendations', 'variant' => 'list']) }}">
          @if($movie->poster_url)
            <img src="{{ $movie->poster_url }}" alt="Poster for {{ $movie->title }}">
          @endif
          <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? '—' }})</div>
          <div class="muted">Weighted score: {{ number_format($movie->weighted_score, 2) }}</div>
        </a>
      @endforeach
    </div>
  @endif

  @if($recommendations->hasPages())
    <div class="pagination" data-testid="recommendations-pagination" data-prev="{{ $recommendations->previousPageUrl() }}" data-next="{{ $recommendations->nextPageUrl() }}">
      <div style="display:flex;gap:10px;">
        @if($recommendations->onFirstPage())
          <span class="muted">Previous</span>
        @else
          <a href="{{ $recommendations->previousPageUrl() }}">Previous</a>
        @endif
        <span>Page {{ $recommendations->currentPage() }} of {{ $recommendations->lastPage() }}</span>
        @if($recommendations->hasMorePages())
          <a href="{{ $recommendations->nextPageUrl() }}">Next</a>
        @else
          <span class="muted">Next</span>
        @endif
      </div>
    </div>
  @endif
@endsection

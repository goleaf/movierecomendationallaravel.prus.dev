@extends('layouts.app')
@section('title','Рекомендации')
@section('content')
<div class="card" style="margin-bottom:16px;">
  <h2>Персональные рекомендации</h2>
  <p class="muted">Алгоритм A/B (device cookie) подбирает топ релизы по IMDb и свежести.</p>
</div>
@if($recommended->isEmpty())
  <div class="muted">Пока нет данных для рекомендаций.</div>
@else
  <div class="grid grid-4">
    @foreach($recommended as $movie)
      <a class="card" href="{{ route('movies.show', $movie) }}">
        @if($movie->poster_url)
          <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? '—' }})</div>
        <div class="muted">IMDb: {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }}</div>
      </a>
    @endforeach
  </div>
@endif

<div class="card" style="margin-top:24px;margin-bottom:12px;">
  <h3>Тренды за 7 дней</h3>
  <p class="muted">Клики рекомендаций по placement'ам. Подробнее — <a href="{{ route('trends') }}">страница трендов</a>.</p>
</div>
@if($trending->isEmpty())
  <div class="muted">Статистика кликов пока не собрана.</div>
@else
  <div class="grid grid-4">
    @foreach($trending as $row)
      @php($movie = $row['movie'])
      <a class="card" href="{{ route('movies.show', $movie) }}">
        @if($movie->poster_url)
          <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $movie->title }}</strong> ({{ $movie->year ?? '—' }})</div>
        <div class="muted">
          @if(!is_null($row['clicks']))
            Клики: {{ $row['clicks'] }}
            @if($movie->imdb_rating)
              • IMDb {{ $movie->imdb_rating }}
            @endif
          @else
            IMDb {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }}
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection

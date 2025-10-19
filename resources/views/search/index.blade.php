@extends('layouts.app')
@section('title','Поиск')
@section('content')
<div class="card">
  <form method="get" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;">
    <input type="text" name="q" value="{{ $q }}" placeholder="Название или tt...">
    <select name="type">
      <option value="">Тип</option>
      <option value="movie" @selected(($type ?? '')==='movie')>Фильмы</option>
      <option value="series" @selected(($type ?? '')==='series')>Сериалы</option>
      <option value="animation" @selected(($type ?? '')==='animation')>Мультики</option>
    </select>
    <input type="text" name="genre" value="{{ $genre }}" placeholder="Жанр">
    <input type="number" name="yf" value="{{ $yf }}" placeholder="Год от">
    <input type="number" name="yt" value="{{ $yt }}" placeholder="Год до">
    <button>Искать</button>
  </form>
</div>
@if($items->isEmpty())
  <div class="muted">Ничего не найдено</div>
@else
  <div class="grid grid-4" style="margin-top:10px;">
    @foreach($items as $m)
      <a class="card" href="{{ route('movies.show',$m) }}">
        @if($m->poster_url)<img src="{{ $m->poster_url }}" alt="{{ $m->title }}"/>@endif
        <div><strong>{{ $m->title }}</strong> ({{ $m->year ?? '—' }})</div>
        <div class="muted">IMDb: {{ $m->imdb_rating ?? '—' }} • {{ $m->imdb_votes ?? 0 }}</div>
      </a>
    @endforeach
  </div>
@endif
@endsection

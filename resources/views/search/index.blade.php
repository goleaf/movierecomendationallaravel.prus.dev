@extends('layouts.app')
@section('title', __('messages.search.title'))
@section('content')
<div class="card">
  <form method="get" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;">
    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('messages.search.form.query_placeholder') }}">
    <select name="type">
      <option value="">{{ __('messages.search.form.type_label') }}</option>
      <option value="movie" @selected(($type ?? '')==='movie')>{{ __('messages.search.form.type_movie') }}</option>
      <option value="series" @selected(($type ?? '')==='series')>{{ __('messages.search.form.type_series') }}</option>
      <option value="animation" @selected(($type ?? '')==='animation')>{{ __('messages.search.form.type_animation') }}</option>
    </select>
    <input type="text" name="genre" value="{{ $genre }}" placeholder="{{ __('messages.search.form.genre_placeholder') }}">
    <input type="number" name="yf" value="{{ $yf }}" placeholder="{{ __('messages.search.form.year_from_placeholder') }}">
    <input type="number" name="yt" value="{{ $yt }}" placeholder="{{ __('messages.search.form.year_to_placeholder') }}">
    <button>{{ __('messages.search.form.submit') }}</button>
  </form>
</div>
@if($items->isEmpty())
  <div class="muted">{{ __('messages.search.empty') }}</div>
@else
  <div class="grid grid-4" style="margin-top:10px;">
    @foreach($items as $m)
      <a class="card" href="{{ route('movies.show', ['movie'=>$m, 'placement'=>'search', 'variant'=>'search']) }}">
        @if($poster = proxy_image_url($m->poster_url))
          <img src="{{ $poster }}" alt="{{ $m->title ? 'Постер фильма «' . $m->title . '»' : 'Постер фильма' }}"/>
        @endif
        <div><strong>{{ $m->title }}</strong> ({{ $m->year ?? __('messages.common.dash') }})</div>
        <div class="muted">{{ __('messages.common.imdb_with_votes_colon', ['rating' => $m->imdb_rating ?? __('messages.common.dash'), 'votes' => $m->imdb_votes ?? 0]) }}</div>
      </a>
    @endforeach
  </div>
@endif
@endsection

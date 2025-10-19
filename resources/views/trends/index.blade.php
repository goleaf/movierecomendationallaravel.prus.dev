@extends('layouts.app')
@section('title','Тренды рекомендаций')
@section('content')
<div class="card" style="margin-bottom:16px;">
  <h2>Тренды рекомендаций</h2>
  <p class="muted">Период: {{ $from }} — {{ $to }} ({{ $days }} дн.)</p>
</div>
@if(collect($items)->isEmpty())
  <div class="muted">Нет данных — проверьте сбор кликов или измените фильтры.</div>
@else
  <div class="grid grid-4">
    @foreach($items as $item)
      <a class="card" href="{{ route('movies.show', ['movie'=>$item->id, 'placement'=>$item->placement ?? 'trends', 'variant'=>$item->variant ?? 'mixed']) }}">
        @if($item->poster_url)
          <img src="{{ $item->poster_url }}" alt="{{ $item->title }}" loading="lazy"/>
        @endif
        <div><strong>{{ $item->title }}</strong> ({{ $item->year ?? '—' }})</div>
        <div class="muted">
          Клики: {{ $item->clicks ?? '—' }}
          @if($item->imdb_rating)
            • IMDb {{ $item->imdb_rating }}
          @endif
          @if($item->imdb_votes)
            • {{ number_format($item->imdb_votes, 0, '.', ' ') }} голосов
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection

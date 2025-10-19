@extends('layouts.app')
@section('title','CTR Analytics')
@section('content')
<div class="card">
  <div class="muted">Период: {{ $from }} — {{ $to }}</div>
  <img src="{{ route('admin.ctr.svg',['from'=>$from,'to'=>$to]) }}" alt="CTR line"/>
  <img src="{{ route('admin.ctr.bars.svg',['from'=>$from,'to'=>$to]) }}" alt="CTR bars" style="margin-top:10px;"/>
  <h3>Итоги A/B</h3>
  <ul>
    @foreach($summary as $s)
      <li>Вариант {{ $s['v'] }} — Imps: {{ $s['imps'] }}, Clicks: {{ $s['clks'] }}, CTR: {{ $s['ctr'] }}%</li>
    @endforeach
  </ul>
</div>
@endsection

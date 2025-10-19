@extends('layouts.app')
@section('title', __('admin.ctr.title'))
@section('meta_robots', 'noindex, nofollow')
@section('content')
<div class="card">
  <div class="muted">{{ __('admin.ctr.period', ['from' => $from, 'to' => $to]) }}</div>
  <img src="{{ route('admin.ctr.svg',['from'=>$from,'to'=>$to]) }}" alt="{{ __('admin.ctr.line_alt') }}"/>
  <img src="{{ route('admin.ctr.bars.svg',['from'=>$from,'to'=>$to]) }}" alt="{{ __('admin.ctr.bars_alt') }}" style="margin-top:10px;"/>
  <h3>{{ __('admin.ctr.ab_summary_heading') }}</h3>
  <ul>
    @foreach($summary as $s)
      <li>{{ __('admin.ctr.ab_summary_item', [
        'variant' => $s['v'],
        'impressions' => $s['imps'],
        'clicks' => $s['clks'],
        'ctr' => $s['ctr'],
      ]) }}</li>
    @endforeach
  </ul>
  <h3>{{ __('admin.ctr.funnels.heading') }}</h3>
  <ul>
    @foreach($funnels as $label => $row)
      <li>
        {{ $label }} — CTR: {{ number_format($row['ctr'], 2) }}%, CUPED: {{ number_format($row['cuped_ctr'], 2) }}%, {{ __('admin.funnel.headers.imps') }}: {{ number_format($row['imps']) }}, {{ __('admin.funnel.headers.clicks') }}: {{ number_format($row['clks']) }}
      </li>
    @endforeach
  </ul>
</div>
@endsection

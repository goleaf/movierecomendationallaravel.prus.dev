@extends('layouts.app')

@section('title', __('messages.works.title'))
@section('content')
<div class="card">
  <h1>{{ __('messages.works.heading') }}</h1>
  <p class="muted">{!! __('messages.works.description_html') !!}</p>
  <div class="markdown">
    {!! $content !!}
  </div>
</div>
@endsection

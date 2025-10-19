@extends('layouts.app')

@section('title','Works Log')
@section('content')
<div class="card">
  <h1>Delivered Works</h1>
  <p class="muted">This page renders the repository <code>WORKS.md</code> file for easy reference inside the admin surface.</p>
  <div class="markdown">
    {!! $content !!}
  </div>
</div>
@endsection

@extends('layouts.app')

@section('title', __('admin.ssr.title'))

@section('content')
  @include('filament.analytics.partials.ssr-overview')
@endsection

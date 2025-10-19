@extends('layouts.app')

@section('title', __('messages.legal.terms.title'))
@section('content')
<div class="card markdown">
    <h1>{{ __('messages.legal.terms.title') }}</h1>
    <p>{{ __('messages.legal.terms.intro') }}</p>

    <h2>{{ __('messages.legal.terms.service_title') }}</h2>
    <p>{{ __('messages.legal.terms.service') }}</p>

    <h2>{{ __('messages.legal.terms.responsibility_title') }}</h2>
    <p>{{ __('messages.legal.terms.responsibility') }}</p>

    <h2>{{ __('messages.legal.terms.changes_title') }}</h2>
    <p>{{ __('messages.legal.terms.changes') }}</p>
</div>
@endsection

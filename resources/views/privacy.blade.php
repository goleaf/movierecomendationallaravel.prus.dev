@extends('layouts.app')

@section('title', __('messages.legal.privacy.title'))
@section('content')
<div class="card markdown">
    <h1>{{ __('messages.legal.privacy.title') }}</h1>
    <p>{{ __('messages.legal.privacy.intro') }}</p>

    <h2>{{ __('messages.legal.privacy.collection_title') }}</h2>
    <p>{{ __('messages.legal.privacy.collection') }}</p>

    <h2>{{ __('messages.legal.privacy.usage_title') }}</h2>
    <p>{{ __('messages.legal.privacy.usage') }}</p>

    <h2>{{ __('messages.legal.privacy.control_title') }}</h2>
    <p>{{ __('messages.legal.privacy.control') }}</p>
</div>
@endsection

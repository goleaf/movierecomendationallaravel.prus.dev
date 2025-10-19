@extends('layouts.app')

@section('title', $message)
@section('meta_description', $message)

@section('content')
    <article class="card" role="alert">
        <h1>{{ $status }} • {{ $message }}</h1>
        <p class="muted">{{ $code }}</p>
        <p>{{ __('If the issue persists, contact support and provide the request ID below so we can help trace the error.') }}</p>
        @if($requestId)
            <p class="muted" data-testid="request-id">{{ __('Request ID: :id', ['id' => $requestId]) }}</p>
        @endif
        @if($documentationUrl)
            <p><a href="{{ $documentationUrl }}">{{ __('View troubleshooting guide') }}</a></p>
        @endif
    </article>
@endsection

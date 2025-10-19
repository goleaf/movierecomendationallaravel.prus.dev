@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="mx-auto max-w-2xl py-24 text-center">
        <p class="text-sm uppercase tracking-widest text-gray-500">{{ __('Error') }} {{ $status }}</p>
        <h1 class="mt-4 text-3xl font-semibold text-gray-100">{{ $headline }}</h1>
        <p class="mt-4 text-base text-gray-300">{{ $message }}</p>

        @if(!empty($errors))
            <div class="mt-8 text-left text-sm text-gray-200">
                <ul class="list-disc space-y-2 pl-6">
                    @foreach($errors as $fieldErrors)
                        @foreach($fieldErrors as $fieldError)
                            <li>{{ $fieldError }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($retryAfter))
            <p class="mt-6 text-sm text-gray-400">{{ __('Retry after :seconds seconds.', ['seconds' => $retryAfter]) }}</p>
        @endif

        @if(!empty($requestId))
            <p class="mt-6 text-xs text-gray-500">{{ __('Request ID: :id', ['id' => $requestId]) }}</p>
        @endif

        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <a href="{{ url('/') }}"
               class="inline-flex items-center rounded-md bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                {{ __('Go back home') }}
            </a>

            @hasSection('errorActions')
                @yield('errorActions')
            @endif
        </div>
    </section>
@endsection

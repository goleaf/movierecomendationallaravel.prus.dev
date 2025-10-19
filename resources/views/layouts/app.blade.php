<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? trim($__env->yieldContent('title', 'MovieRec')) }}</title>
        <meta name="description" content="{{ $metaDescription ?? trim($__env->yieldContent('meta_description', 'Подборки, тренды и рекомендации')) }}">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $ogTitle ?? trim($__env->yieldContent('og_title', $__env->yieldContent('title', 'MovieRec'))) }}">
        <meta property="og:description" content="{{ $ogDescription ?? trim($__env->yieldContent('og_desc', $__env->yieldContent('meta_description', 'Подборки и рекомендации'))) }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ $ogImage ?? $__env->yieldContent('og_image', asset('img/og_default.jpg')) }}">
        <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "WebSite",
                "name": "MovieRec",
                "url": "{{ url('/') }}",
                "potentialAction": {
                    "@@type": "SearchAction",
                    "target": "{{ url('/search') }}?q={query}",
                    "query-input": "required name=query"
                }
            }
        </script>
        @unless(app()->runningUnitTests())
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endunless
        @livewireStyles
        @stack('styles')
    </head>
    <body class="bg-slate-950 font-sans text-slate-100 antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-1 pb-8">
                <a href="{{ url('/') }}" class="text-2xl font-semibold text-sky-400 transition hover:text-sky-300">MovieRec</a>
                <span class="text-sm text-slate-400">SSR • SVG-графики</span>
            </header>

            <main class="flex-1 space-y-10">
                {{ $slot ?? '' }}
                @yield('content')
            </main>

            <footer class="pt-8 text-sm text-slate-500">© {{ date('Y') }} MovieRec</footer>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ trim($__env->yieldContent('title', $title ?? 'MovieRec')) }}</title>
    <meta name="description" content="{{ trim($__env->yieldContent('meta_description', $metaDescription ?? 'Подборки, тренды и рекомендации')) }}" />
    <meta name="color-scheme" content="dark" />
    <link rel="canonical" href="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title', $ogTitle ?? trim($__env->yieldContent('title', $title ?? 'MovieRec')))) }}" />
    <meta property="og:description" content="{{ trim($__env->yieldContent('og_desc', $ogDescription ?? trim($__env->yieldContent('meta_description', $metaDescription ?? 'Подборки и рекомендации')))) }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ $ogImage ?? trim($__env->yieldContent('og_image', asset('img/og_default.jpg'))) }}" />
    @php($hasViteAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @if ($hasViteAssets)
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-10 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ url('/') }}" class="text-lg font-semibold text-slate-50 transition hover:text-sky-300">MovieRec</a>
            <span class="text-sm text-slate-400">SSR • SVG-графики</span>
        </header>

        <main class="flex-1">
            @if (trim($__env->yieldContent('content')) !== '')
                {{ $slot ?? '' }}
                @yield('content')
            @elseif (isset($slot))
                {{ $slot }}
            @endif
        </main>

        <footer class="mt-12 border-t border-slate-800 pt-6 text-sm text-slate-500">
            © {{ now()->year }} MovieRec
        </footer>
    </div>

    @stack('scripts')
    @livewireScripts
</body>
</html>

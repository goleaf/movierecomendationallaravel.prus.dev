<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $defaultTitle = __('messages.app.default_title');
        $pageTitle = trim($__env->yieldContent('title', $defaultTitle));

        if (! $__env->hasSection('title')) {
            $__env->startSection('title', $pageTitle);
            $__env->stopSection();
        }

        $defaultDescription = $metaDescription ?? __('messages.app.meta_description');
        $metaDescriptionContent = trim($__env->yieldContent('meta_description', $defaultDescription));

        if (! $__env->hasSection('description')) {
            $__env->startSection('description', $metaDescriptionContent);
            $__env->stopSection();
        }

        $keywordsFallback = $metaKeywords ?? __('messages.app.meta_keywords');

        if (! $__env->hasSection('keywords') && filled($keywordsFallback)) {
            $__env->startSection('keywords', $keywordsFallback);
            $__env->stopSection();
        }

        $authorFallback = $metaAuthor ?? __('messages.app.meta_author');

        if (! $__env->hasSection('author')) {
            $__env->startSection('author', $authorFallback);
            $__env->stopSection();
        }

        $imageFallback = $metaImage ?? asset('img/og_default.jpg');

        if (! $__env->hasSection('image')) {
            $__env->startSection('image', $imageFallback);
            $__env->stopSection();
        }

        $typeFallback = $metaType ?? 'website';

        if (! $__env->hasSection('type')) {
            $__env->startSection('type', $typeFallback);
            $__env->stopSection();
        }

        $categoryFallback = $metaCategory ?? __('messages.app.meta_category');

        if (! $__env->hasSection('category')) {
            $__env->startSection('category', $categoryFallback);
            $__env->stopSection();
        }

        $dateFallback = $metaDate ?? now()->toIso8601String();

        if (! $__env->hasSection('date')) {
            $__env->startSection('date', $dateFallback);
            $__env->stopSection();
        }

        $pageTitle = trim($__env->yieldContent('title'));
        $pageDescription = trim($__env->yieldContent('description'));
        $pageImage = trim($__env->yieldContent('image'));
    @endphp

    @filamentSeo
    <x-filament-meta />

    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $pageTitle,
            'url' => url('/'),
            'description' => $pageDescription,
            'image' => $pageImage,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/search').'?q={query}',
                'query-input' => 'required name=query',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <style>
        :root {color-scheme: dark;}
        body {margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, Noto Sans; background: #0b0c0f; color: #e6e7e8;}
        a {color: #9fc5ff; text-decoration: none;}
        .container {max-width: 1200px; margin: 0 auto; padding: 20px;}
        .grid {display: grid; gap: 14px;}
        .grid-4 {grid-template-columns: repeat(4, 1fr);}
        .card {background: #11151a; border: 1px solid #171b22; border-radius: 14px; padding: 12px;}
        .muted {color: #98a2b3; font-size: .92em;}
        img {max-width: 100%; border-radius: 10px; display: block;}
        .markdown h1 {font-size: 1.8rem; margin-top: 0;}
        .markdown h2 {margin-top: 1.6rem; font-size: 1.4rem;}
        .markdown h3 {margin-top: 1.2rem; font-size: 1.15rem;}
        .markdown ul {padding-left: 1.2rem;}
        .markdown li {margin-bottom: .35rem; line-height: 1.5;}
        @media (max-width: 1000px) {.grid-4 {grid-template-columns: repeat(2, 1fr);} }
        @media (max-width: 560px) {.grid-4 {grid-template-columns: 1fr;} }
    </style>
</head>
<body>
    <div class="container">
        <header class="mb">
            <a href="{{ url('/') }}"><strong>{{ __('messages.app.name') }}</strong></a>
            <span class="muted">{{ __('messages.app.tagline') }}</span>
        </header>

        @yield('content')

        <footer class="muted">{{ __('messages.app.footer', ['year' => date('Y')]) }}</footer>
    </div>
</body>
</html>

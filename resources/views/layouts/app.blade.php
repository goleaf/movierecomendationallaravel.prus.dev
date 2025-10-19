<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', __('messages.app.default_title'))</title>
<meta name="description" content="@yield('meta_description', __('messages.app.meta_description'))">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:title" content="@yield('og_title', $__env->yieldContent('title', __('messages.app.default_title')))">
<meta property="og:description" content="@yield('og_desc', $__env->yieldContent('meta_description', __('messages.app.og_description')))">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="@yield('og_image', asset('img/og_default.jpg'))">
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebSite","name":"{{ __('messages.app.name') }}","url":"{{ url('/') }}",
"potentialAction":{"@type":"SearchAction","target":"{{ url('/search') }}?q={query}","query-input":"required name=query"}}
</script>
<style>
:root{color-scheme:dark}body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,Noto Sans;background:#0b0c0f;color:#e6e7e8}
a{color:#9fc5ff;text-decoration:none}.container{max-width:1200px;margin:0 auto;padding:20px}
.grid{display:grid;gap:14px}.grid-4{grid-template-columns:repeat(4,1fr)}.card{background:#11151a;border:1px solid #171b22;border-radius:14px;padding:12px}
.muted{color:#98a2b3;font-size:.92em}img{max-width:100%;border-radius:10px;display:block}
@media(max-width:1000px){.grid-4{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid-4{grid-template-columns:1fr}}
</style>
</head>
<body><div class="container">
<header class="mb"><a href="{{ url('/') }}"><strong>{{ __('messages.app.name') }}</strong></a> <span class="muted">{{ __('messages.app.tagline') }}</span></header>
@yield('content')
<footer class="muted">{{ __('messages.app.footer', ['year' => date('Y')]) }}</footer>
</div></body></html>

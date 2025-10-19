<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title','MovieRec')</title>
<meta name="description" content="@yield('meta_description','Подборки, тренды и рекомендации')">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title','MovieRec')))">
<meta property="og:description" content="@yield('og_desc', trim($__env->yieldContent('meta_description','Подборки и рекомендации')))">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="@yield('og_image', asset('img/og_default.jpg'))">
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebSite","name":"MovieRec","url":"{{ url('/') }}",
"potentialAction":{"@type":"SearchAction","target":"{{ url('/search') }}?q={query}","query-input":"required name=query"}}
</script>
<style>
:root{color-scheme:dark}body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,Noto Sans;background:#0b0c0f;color:#e6e7e8}
a{color:#9fc5ff;text-decoration:none}.container{max-width:1200px;margin:0 auto;padding:20px}
.grid{display:grid;gap:14px}.grid-4{grid-template-columns:repeat(4,1fr)}.card{background:#11151a;border:1px solid #171b22;border-radius:14px;padding:12px}
.muted{color:#98a2b3;font-size:.92em}img{max-width:100%;border-radius:10px;display:block}
.markdown h1{font-size:1.8rem;margin-top:0}
.markdown h2{margin-top:1.6rem;font-size:1.4rem}
.markdown h3{margin-top:1.2rem;font-size:1.15rem}
.markdown ul{padding-left:1.2rem}
.markdown li{margin-bottom:.35rem;line-height:1.5}
@media(max-width:1000px){.grid-4{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid-4{grid-template-columns:1fr}}
</style>
</head>
<body><div class="container">
<header class="mb"><a href="{{ url('/') }}"><strong>MovieRec</strong></a> <span class="muted">SSR • SVG-графики</span></header>
@yield('content')
<footer class="muted">© {{ date('Y') }} MovieRec</footer>
</div></body></html>

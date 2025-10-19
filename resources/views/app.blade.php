<!DOCTYPE html>
@php
    $htmlLocale = str_replace('_', '-', app()->getLocale());
    $rtlLocales = ['ar', 'dv', 'fa', 'he', 'ku', 'ps', 'ur', 'yi'];
    $htmlDirection = in_array(\Illuminate\Support\Str::before($htmlLocale, '-'), $rtlLocales, true) ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $htmlLocale }}" dir="{{ $htmlDirection }}" class="dark bg-white dark:bg-black">
<head>
    @php
        use App\Settings\SiteSettings;
        $siteSettings = app(SiteSettings::class);
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @if($siteSettings->favicon)
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset("storage/" . $siteSettings->favicon) }}">
    @endif
    @if(config('app.env') !== 'production')
        <meta name="robots" content="noindex, nofollow">
    @endif

    <title inertia>{{ $siteSettings->name }}</title>
    <meta name="title" content="{{ $siteSettings->name }}">
    <meta name="description" content="{{ $siteSettings->description }}">
    <meta property="og:site_name" content="{{ $siteSettings->name }}">
    <meta property="og:title" content="{{ $siteSettings->name }}">
    <meta property="og:description" content="{{ $siteSettings->description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ $htmlLocale }}">
    @if($siteSettings->og_image)
        <meta property="og:image" content="{{ asset("storage/" . $siteSettings->og_image) }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="{{ $siteSettings->name }}">
    <meta name="twitter:title" content="{{ $siteSettings->name }}">
    <meta name="twitter:description" content="{{ $siteSettings->description }}">
    @if($siteSettings->og_image)
        <meta name="twitter:image" content="{{ asset("storage/" . $siteSettings->og_image) }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">

    @viteReactRefresh
    @vite(['resources/js/App.tsx', "resources/js/Pages/{$page['component']}.tsx", "resources/css/app.css"])
    @inertiaHead
    @stack('meta')

    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteSettings->name,
            'url' => url('/'),
        ];
        if (!empty($siteSettings->description)) {
            $structuredData['description'] = $siteSettings->description;
        }
    @endphp
    <script nonce="{{ csp_nonce() }}" type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>

    {!! $siteSettings->header_scripts !!}
</head>
<body class="text-white font-sans">
@routes
@inertia

{!! $siteSettings->footer_scripts !!}
</body>
</html>

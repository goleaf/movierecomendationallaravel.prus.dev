@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($movies as $movie)
    <url>
        <loc>{{ route('movies.show', $movie) }}</loc>
        @if($movie->updated_at !== null)
        <lastmod>{{ $movie->updated_at->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>

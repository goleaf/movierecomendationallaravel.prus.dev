# URL Parameter Recipes

These snippets show how we reference route and query parameters both in Blade views and middleware. They are designed to be copy-paste ready when someone needs to react to the current URL.

## Reading parameters inside Blade

```blade
@php
    $genre = request()->query('genre');
    $year = request()->integer('year');
    $movie = request()->route('movie');
@endphp

<span>
    Filtering by: {{ $genre ?? 'any genre' }} / {{ $year ?? 'any year' }}
</span>

<a href="{{ route('search', array_filter(['genre' => $genre, 'year' => $year, 'type' => 'movie'])) }}">
    Keep the current filters but show movie results
</a>

<a href="{{ request()->fullUrlWithQuery(['type' => 'series']) }}">
    Stay on the page and only change the `type` query parameter
</a>

@if ($movie)
    <div>Currently nested under movie {{ $movie }}.</div>
@endif
```

## Reading parameters in middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CaptureFilters
{
    public function handle(Request $request, Closure $next)
    {
        $genre = $request->query('genre');
        $year = $request->integer('year');
        $movie = $request->route('movie');

        if ($movie !== null) {
            $request->attributes->set('movieId', (int) $movie);
        }

        logger()->debug('filters from URL', compact('genre', 'year', 'movie'));

        return $next($request);
    }
}
```

Use `request()->query()` for query strings, `request()->route()` (or `$request->route()` in middleware) for named route placeholders, and `fullUrlWithQuery()` when you want to keep the current page but change a few query parameters.

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request)
    {
        $url = $request->query('url');

        if (! is_string($url) || $url === '') {
            abort(400, 'Image URL is required.');
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid image URL.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            abort(400, 'Unsupported image URL scheme.');
        }

        $appUrl = (string) config('app.url', '');
        $appHost = $appUrl !== '' ? parse_url($appUrl, PHP_URL_HOST) : null;
        $host = parse_url($url, PHP_URL_HOST);

        if ($appHost !== null && $host !== null && strcasecmp($host, $appHost) === 0) {
            abort(400, 'Proxying local URLs is not allowed.');
        }

        $response = Http::timeout(10)
            ->withHeaders([
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'User-Agent' => 'MovieRec Image Proxy',
            ])
            ->get($url);

        if ($response->failed()) {
            abort(502, 'Unable to fetch the requested image.');
        }

        $contentType = $response->header('Content-Type', 'image/jpeg');

        return response($response->body(), Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

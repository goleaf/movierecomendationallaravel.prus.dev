<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ProxyImageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $type = $request->query('type', 'poster');

        if (! in_array($type, ['poster', 'backdrop'], true)) {
            abort(404);
        }

        $url = $request->query('url');

        if (! is_string($url) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        $response = Http::timeout(10)->accept('image/*')->get($url);

        if (! $response->successful()) {
            abort(404);
        }

        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type', 'image/jpeg'))
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Image-Type', $type);
    }
}

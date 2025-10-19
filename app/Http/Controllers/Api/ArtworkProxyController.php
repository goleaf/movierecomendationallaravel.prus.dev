<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ArtworkProxyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $source = $request->query('src');

        if (! is_string($source) || blank($source)) {
            abort(404);
        }

        $url = parse_url($source);

        if ($url === false || ! isset($url['scheme']) || ! in_array($url['scheme'], ['http', 'https'], true)) {
            abort(422, 'Invalid source.');
        }

        $response = Http::timeout(10)->accept('*/*')->get($source);

        if ($response->failed()) {
            abort($response->status());
        }

        $headers = [];

        foreach (['Content-Type', 'Cache-Control', 'Content-Length', 'Last-Modified'] as $header) {
            $value = $response->header($header);

            if ($value !== null) {
                $headers[$header] = $value;
            }
        }

        return response($response->body(), $response->status(), $headers);
    }
}

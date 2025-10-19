<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(404);
        }

        $source = base64_decode($token, true);

        if (! is_string($source) || $source === '' || ! filter_var($source, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        try {
            $response = Http::timeout(10)
                ->accept('image/*')
                ->get($source);
        } catch (ConnectionException) {
            abort(404);
        }

        if ($response->failed()) {
            abort(404);
        }

        return response($response->body(), Response::HTTP_OK, [
            'Content-Type' => $response->header('Content-Type', 'image/jpeg'),
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}

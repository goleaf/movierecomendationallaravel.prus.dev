<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProxyImageController extends Controller
{
    public function __invoke(Request $request, string $encoded)
    {
        if (! $request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $target = $this->decodeTargetUrl($encoded);

        if (blank($target) || ! filter_var($target, FILTER_VALIDATE_URL) || ! Str::startsWith($target, ['http://', 'https://'])) {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            $httpResponse = Http::timeout(10)
                ->connectTimeout(5)
                ->accept('image/avif,image/webp,image/*,*/*;q=0.8')
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Laravel').' image-proxy',
                ])
                ->get($target);
        } catch (Throwable) {
            abort(Response::HTTP_BAD_GATEWAY);
        }

        if ($httpResponse->failed()) {
            abort(Response::HTTP_BAD_GATEWAY);
        }

        return response($httpResponse->body(), Response::HTTP_OK)
            ->header('Content-Type', $httpResponse->header('Content-Type', 'image/jpeg'))
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function decodeTargetUrl(string $encoded): ?string
    {
        $normalized = strtr($encoded, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }
}

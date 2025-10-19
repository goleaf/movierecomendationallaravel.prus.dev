<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Http\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PosterProxyController extends Controller
{
    private const DEFAULT_FALLBACK = 'img/og-default.svg';

    public function __invoke(Request $request): BinaryFileResponse|StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $validated = $request->validate([
            'src' => ['required', 'url'],
            'fallback' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_\-\/\.]+$/'],
        ]);

        try {
            $response = Policy::external()
                ->replaceHeaders([
                    'Accept' => 'image/*,*/*',
                    'User-Agent' => config('app.name').'/PosterProxy',
                ])
                ->withOptions(['stream' => true])
                ->get($validated['src']);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackResponse($validated['fallback'] ?? null, 502);
        }

        if ($response->failed()) {
            return $this->fallbackResponse($validated['fallback'] ?? null, $response->status());
        }

        $psrResponse = $response->toPsrResponse();
        $stream = $psrResponse->getBody();

        $headers = [
            'Content-Type' => $psrResponse->getHeaderLine('Content-Type') ?: 'image/jpeg',
            'Cache-Control' => $psrResponse->getHeaderLine('Cache-Control') ?: 'public, max-age=3600',
        ];

        $contentLength = $psrResponse->getHeaderLine('Content-Length');
        if ($contentLength !== '') {
            $headers['Content-Length'] = $contentLength;
        }

        $lastModified = $psrResponse->getHeaderLine('Last-Modified');
        if ($lastModified !== '') {
            $headers['Last-Modified'] = $lastModified;
        }

        return response()->stream(static function () use ($stream): void {
            while (! $stream->eof()) {
                echo $stream->read(1024);
            }
        }, $psrResponse->getStatusCode(), $headers);
    }

    private function fallbackResponse(?string $requestedFallback, int $status): BinaryFileResponse
    {
        $path = $this->resolveFallbackPath($requestedFallback);

        $response = response()->file($path, [
            'Cache-Control' => 'public, max-age=600',
        ]);

        $response->setStatusCode($status >= 400 ? $status : 502);

        return $response;
    }

    private function resolveFallbackPath(?string $requestedFallback): string
    {
        if (is_string($requestedFallback) && $requestedFallback !== '') {
            $normalized = ltrim($requestedFallback, '/');

            if (preg_match('/^[A-Za-z0-9_\-\/\.]+$/', $normalized) === 1) {
                $candidate = public_path($normalized);

                if (File::exists($candidate)) {
                    return $candidate;
                }
            }
        }

        $default = public_path(self::DEFAULT_FALLBACK);

        if (File::exists($default)) {
            return $default;
        }

        abort(404, 'Fallback asset not found.');
    }
}

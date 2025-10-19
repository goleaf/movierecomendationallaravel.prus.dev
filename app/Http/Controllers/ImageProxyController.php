<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CacheProxiedImage;
use App\Support\ImageProxyStorage;
use App\Support\ProxyImageHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ImageProxyController extends Controller
{
    public function __invoke(Request $request, ImageProxyStorage $storage): StreamedResponse
    {
        $hash = (string) $request->route('hash');
        $encodedSource = $request->query('source');

        if (! is_string($encodedSource) || $encodedSource === '') {
            abort(404);
        }

        try {
            $sourceUrl = ProxyImageHelper::decodeSource($encodedSource);
        } catch (Throwable $exception) {
            report($exception);

            abort(404);
        }

        if ($hash !== ProxyImageHelper::hashFor($sourceUrl)) {
            abort(404);
        }

        if (! $storage->isFresh($sourceUrl)) {
            try {
                CacheProxiedImage::dispatchSync($sourceUrl);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (! $storage->exists($sourceUrl)) {
            abort(404);
        }

        $headers = config('image-proxy.headers', []);

        return $storage->response($sourceUrl, $headers);
    }
}

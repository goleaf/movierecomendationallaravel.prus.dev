<?php

declare(strict_types=1);

namespace App\Support\Storage;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlGenerator;

class ProxyImageUrlGenerator
{
    public function __construct(private readonly UrlGenerator $urlGenerator) {}

    public function generate(string $source): string
    {
        $ttlMinutes = max(1, (int) config('app.proxy_image_ttl', 60));
        $expiresAt = CarbonImmutable::now()->addMinutes($ttlMinutes);

        return $this->urlGenerator->temporarySignedRoute('images.proxy', $expiresAt, [
            'token' => base64_encode($source),
        ]);
    }
}

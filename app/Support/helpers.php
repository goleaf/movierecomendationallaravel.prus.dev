<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

if (! function_exists('poster_srcset')) {
    function poster_srcset(?string $url, array $widths = [320, 480, 640, 960]): ?string
    {
        if ($url === null) {
            return null;
        }

        $normalized = trim($url);

        if ($normalized === '') {
            return null;
        }

        $widths = collect($widths)
            ->map(fn ($width) => (int) $width)
            ->filter(fn (int $width) => $width > 0)
            ->unique()
            ->sort()
            ->values();

        if ($widths->isEmpty()) {
            return $normalized.' 1x';
        }

        $buildFromPath = static function (string $pattern, callable $builder) use ($normalized, $widths): ?string {
            if (! preg_match($pattern, $normalized, $matches)) {
                return null;
            }

            $entries = [];

            foreach ($widths as $width) {
                $entries[] = $builder((int) $width, $matches).' '.$width.'w';
            }

            return implode(', ', $entries);
        };

        $pathSrcset = $buildFromPath('#/(\d{2,4})/(\d{2,4})(\?.*)?$#', static function (int $width, array $matches): string {
            $originalWidth = (int) $matches[1];
            $originalHeight = (int) $matches[2];
            $ratio = $originalWidth > 0 ? max($originalHeight / $originalWidth, 0.1) : 1.5;
            $height = (int) round($width * $ratio);

            return preg_replace(
                '#/(\d{2,4})/(\d{2,4})(\?.*)?$#',
                sprintf('/%d/%d$3', $width, max($height, 1)),
                $normalized
            );
        });

        if ($pathSrcset !== null) {
            return $pathSrcset;
        }

        if (preg_match('#([?&](?:w|width)=(\d+))#i', $normalized, $widthMatch)) {
            $ratio = 1.5;

            if (preg_match('#([?&](?:h|height)=(\d+))#i', $normalized, $heightMatch)) {
                $origWidth = (int) $widthMatch[2];
                $origHeight = (int) $heightMatch[2];
                $ratio = $origWidth > 0 ? max($origHeight / $origWidth, 0.1) : $ratio;
            }

            $entries = [];

            foreach ($widths as $width) {
                $height = (int) round($width * $ratio);
                $entries[] = preg_replace(
                    ['#([?&](?:w|width)=)(\d+)#i', '#([?&](?:h|height)=)(\d+)#i'],
                    ['$1'.$width, '$1'.max($height, 1)],
                    $normalized
                ).' '.$width.'w';
            }

            return implode(', ', $entries);
        }

        return $normalized.' 1x, '.$normalized.' 2x';
    }
}

if (!function_exists('device_id')) {
    function device_id(): string {
        $key='did'; $did=request()->cookie($key);
        if (!$did) { $did='d_'.Str::uuid()->toString(); Cookie::queue(Cookie::make($key,$did,60*24*365*5)); }
        return $did;
    }
}

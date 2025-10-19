<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateSitemap;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sections = GenerateSitemap::dispatchSync();

        $sitemaps = [];

        foreach ($sections as $key => $section) {
            $sitemaps[] = [
                'loc' => route('sitemap.section', ['section' => $key]),
                'lastmod' => $section['lastmod'] ?? null,
            ];
        }

        return response()
            ->view('sitemaps.index', ['sitemaps' => $sitemaps])
            ->header('Content-Type', 'application/xml');
    }

    public function section(string $section): Response
    {
        $sections = GenerateSitemap::dispatchSync();

        if (! array_key_exists($section, $sections)) {
            abort(404);
        }

        return response()
            ->view('sitemaps.section', [
                'items' => $sections[$section]['items'],
            ])
            ->header('Content-Type', 'application/xml');
    }
}

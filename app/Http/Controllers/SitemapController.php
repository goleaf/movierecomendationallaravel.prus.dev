<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $entries = Movie::query()
            ->select('type')
            ->selectRaw('MAX(updated_at) as last_modified')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->map(function (object $row): array {
                $lastModified = $row->last_modified !== null
                    ? Carbon::parse($row->last_modified)->toImmutable()
                    : null;

                return [
                    'type' => (string) $row->type,
                    'loc' => URL::route('sitemaps.type', ['type' => $row->type]),
                    'lastmod' => $lastModified,
                ];
            })
            ->all();

        return response()
            ->view('sitemap.index', ['entries' => $entries])
            ->header('Content-Type', 'application/xml');
    }

    public function type(string $type): Response
    {
        $normalizedType = trim($type);

        if ($normalizedType === '' || ! Movie::query()->where('type', $normalizedType)->exists()) {
            abort(404);
        }

        $movies = Movie::query()
            ->where('type', $normalizedType)
            ->orderByDesc('updated_at')
            ->select(['id', 'updated_at'])
            ->cursor();

        return response()
            ->view('sitemap.type', [
                'type' => $normalizedType,
                'movies' => $movies,
            ])
            ->header('Content-Type', 'application/xml');
    }
}

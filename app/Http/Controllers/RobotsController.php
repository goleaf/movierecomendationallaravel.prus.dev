<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $content = implode(PHP_EOL, [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}

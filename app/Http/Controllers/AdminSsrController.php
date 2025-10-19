<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\SsrDashboardService;
use Illuminate\Contracts\View\View;

final class AdminSsrController extends Controller
{
    public function __construct(private readonly SsrDashboardService $dashboard)
    {
    }

    public function __invoke(): View
    {
        $overview = $this->dashboard->overview();

        return view('admin.ssr', [
            'summary' => $overview['summary'],
            'trend' => $overview['trend'],
            'drops' => $overview['drops'],
            'source' => $overview['source'],
            'lastUpdated' => $overview['last_updated'],
        ]);
    }
}

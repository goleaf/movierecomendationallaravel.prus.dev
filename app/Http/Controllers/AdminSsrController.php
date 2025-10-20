<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\SsrMetricsAggregator;
use Illuminate\Contracts\View\View;

class AdminSsrController extends Controller
{
    public function __construct(private readonly SsrMetricsAggregator $analytics) {}

    public function __invoke(): View
    {
        return view('admin.ssr', [
            'summary' => $this->analytics->summary(),
            'trend' => $this->analytics->trend(),
            'drops' => $this->analytics->dropRows(),
        ]);
    }
}

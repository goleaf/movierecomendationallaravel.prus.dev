<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\SsrAnalyticsService;
use Illuminate\Contracts\View\View;

class AdminSsrController extends Controller
{
    public function __construct(private readonly SsrAnalyticsService $analytics) {}

    public function __invoke(): View
    {
        return view('admin.ssr', [
            'headline' => $this->analytics->headline(),
            'trend' => $this->analytics->trend(),
            'drops' => $this->analytics->dropRows(),
        ]);
    }
}

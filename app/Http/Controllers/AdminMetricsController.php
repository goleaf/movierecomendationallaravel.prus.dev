<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdminMetricsController extends Controller
{
    public function index(): View
    {
        $queueCount = (int) (DB::table('jobs')->count() ?? 0);
        $failed = (int) (DB::table('failed_jobs')->count() ?? 0);
        $processed = (int) (DB::table('job_batches')->count() ?? 0);

        $horizon = ['workload'=>null,'supervisors'=>null];
        try {
            $workload = Redis::hgetall('horizon:workload');
            $super = Redis::smembers('horizon:supervisors');
            if (!empty($workload)) $horizon['workload'] = $workload;
            if (!empty($super)) $horizon['supervisors'] = $super;
        } catch (\Throwable $e) {}

        return view('admin.metrics', compact('queueCount','failed','processed','horizon'));
    }
}

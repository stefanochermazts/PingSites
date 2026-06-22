<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Services\StatusPageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function index(StatusPageService $statusPageService): View
    {
        $data = Cache::remember(StatusPageService::cacheKey(), 60, fn () => $statusPageService->data());

        return view('status.index', $data);
    }

    public function show(Monitor $monitor, StatusPageService $statusPageService): View
    {
        abort_unless($monitor->published, 404);

        $data = Cache::remember(
            StatusPageService::monitorCacheKey($monitor),
            60,
            fn () => $statusPageService->monitorDetail($monitor),
        );

        return view('status.show', $data);
    }
}

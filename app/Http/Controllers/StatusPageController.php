<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\StatusPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function redirectToDefault(StatusPageService $statusPageService): RedirectResponse
    {
        return redirect()->route('status.show', $statusPageService->defaultPage());
    }

    public function show(StatusPage $statusPage, StatusPageService $statusPageService): View
    {
        $data = Cache::remember(
            StatusPageService::cacheKey($statusPage),
            60,
            fn () => $statusPageService->data($statusPage),
        );

        return view('status.index', $data);
    }

    public function monitorShow(
        StatusPage $statusPage,
        Monitor $monitor,
        StatusPageService $statusPageService,
    ): View {
        abort_unless($statusPageService->monitorBelongsToPage($monitor, $statusPage), 404);

        $data = Cache::remember(
            StatusPageService::monitorCacheKey($statusPage, $monitor),
            60,
            fn () => $statusPageService->monitorDetail($statusPage, $monitor),
        );

        return view('status.show', $data);
    }
}

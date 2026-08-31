<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Services\StatusPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function redirectToDefault(StatusPageService $statusPageService): RedirectResponse
    {
        return redirect()->route('status.show', $statusPageService->defaultPage());
    }

    public function show(Request $request, StatusPage $statusPage, StatusPageService $statusPageService): View
    {
        $data = Cache::remember(
            StatusPageService::cacheKey($statusPage),
            60,
            fn () => $statusPageService->data($statusPage),
        );

        $status = $request->query('status');
        $publication = $request->query('pubblicazione');

        $data = $statusPageService->applyStatusFilter(
            $data,
            is_string($status) ? $status : null,
            $statusPage,
            is_string($publication) ? $publication : null,
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

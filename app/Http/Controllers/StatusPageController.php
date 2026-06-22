<?php

namespace App\Http\Controllers;

use App\Services\StatusPageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StatusPageController extends Controller
{
    public function __invoke(StatusPageService $statusPageService): View
    {
        $data = Cache::remember(StatusPageService::cacheKey(), 60, fn () => $statusPageService->data());

        return view('status.index', $data);
    }
}

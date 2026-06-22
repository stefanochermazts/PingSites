<?php

use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/status');

Route::get('/status', [StatusPageController::class, 'redirectToDefault'])->name('status');
Route::get('/status/{statusPage:slug}', [StatusPageController::class, 'show'])->name('status.show');
Route::get('/status/{statusPage:slug}/monitors/{monitor}', [StatusPageController::class, 'monitorShow'])->name('status.monitor');

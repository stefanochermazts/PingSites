<?php

use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/status');

Route::get('/status', [StatusPageController::class, 'index'])->name('status');
Route::get('/status/monitors/{monitor}', [StatusPageController::class, 'show'])->name('status.monitor');

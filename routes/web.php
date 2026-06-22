<?php

use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/status');

Route::get('/status', StatusPageController::class)->name('status');

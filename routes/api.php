<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// PUBLIC ENDPOINTS: Protected with strict authentication rate limiters
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware('throttle:login');

// PROTECTED ENDPOINTS: Throttling applied alongside Sanctum authentication
// AFTER (Correct execution order)
Route::middleware(['throttle:logout', 'auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
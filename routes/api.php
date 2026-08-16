<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ChatController;

// PUBLIC ENDPOINTS: Protected with strict authentication rate limiters
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/auth/login', [AuthController::class, 'login'])
    ->name('login')
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/chat/send', [ChatController::class, 'sendMessage']);
    Route::get('/chat/stream', [ChatController::class, 'stream']);
});

Route::middleware(['throttle:logout', 'auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
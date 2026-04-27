<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\InternalBookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Book Service
|--------------------------------------------------------------------------
| Public      : no auth required
| Admin       : Bearer JWT (RS256) + role === 'admin'
| Internal    : X-Service-Key header (called by Checkout / other services)
*/

// ── Public ─────────────────────────────────────────────────────────────
Route::get('/books',      [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show']);

// ── Admin ───────────────────────────────────────────────────────────────
Route::middleware(['jwt.verify', 'admin'])->group(function () {
    Route::post  ('/books',      [BookController::class, 'store']);
    Route::put   ('/books/{id}', [BookController::class, 'update']);
    Route::delete('/books/{id}', [BookController::class, 'destroy']);
});

// ── Internal (service-to-service) ───────────────────────────────────────
Route::middleware('service.key')->prefix('internal')->group(function () {
    Route::get  ('/books/{id}/stock', [InternalBookController::class, 'getStock']);
    Route::patch('/books/{id}/stock', [InternalBookController::class, 'updateStock']);
});

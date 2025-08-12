<?php

use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/register', [AuthController::class, 'register']);
});


Route::middleware(['auth:sanctum'])->prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'all']);
    Route::post('/', [TenantController::class, 'create']);
    Route::get('/{id}', [TenantController::class, 'show']);
    Route::delete('/{id}', [TenantController::class, 'delete']);
});

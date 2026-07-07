<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PermissionMatrixController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    Route::middleware('throttle:auth-login')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    Route::middleware('throttle:auth-recovery')->group(function (): void {
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/supervisor/recover-password', [AuthController::class, 'recoverSupervisorPassword']);
    });

    Route::middleware(['auth:sanctum', 'active', 'platform'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('me/profile', [ProfileController::class, 'show']);
        Route::patch('me/profile', [ProfileController::class, 'update']);

        Route::get('users/dashboard', [UserController::class, 'dashboard'])
            ->middleware('permission:users.view');
        Route::get('permissions/matrix', PermissionMatrixController::class)
            ->middleware('permission:users.view');

        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:users.create');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view');
        Route::patch('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update');
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:users.suspend');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])
            ->middleware('permission:users.suspend');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete');
    });
});

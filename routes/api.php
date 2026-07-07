<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PermissionMatrixController;
use App\Http\Controllers\Api\V1\PharmacyController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RegionController;
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

        Route::get('regions', [RegionController::class, 'index'])
            ->middleware('permission:regions.view');
        Route::post('regions', [RegionController::class, 'store'])
            ->middleware('permission:regions.manage');
        Route::get('regions/{region}', [RegionController::class, 'show'])
            ->middleware('permission:regions.view');
        Route::patch('regions/{region}', [RegionController::class, 'update'])
            ->middleware('permission:regions.manage');
        Route::post('regions/{region}/sub-regions', [RegionController::class, 'storeSubRegion'])
            ->middleware('permission:regions.manage');
        Route::patch('sub-regions/{subRegion}', [RegionController::class, 'updateSubRegion'])
            ->middleware('permission:regions.manage');

        Route::get('companies', [CompanyController::class, 'index'])
            ->middleware('permission:companies.view');
        Route::post('companies', [CompanyController::class, 'store'])
            ->middleware('permission:companies.manage');
        Route::get('companies/{company}', [CompanyController::class, 'show'])
            ->middleware('permission:companies.view');
        Route::patch('companies/{company}', [CompanyController::class, 'update'])
            ->middleware('permission:companies.manage');
        Route::post('companies/{company}/suspend', [CompanyController::class, 'suspend'])
            ->middleware('permission:companies.manage');
        Route::post('companies/{company}/activate', [CompanyController::class, 'activate'])
            ->middleware('permission:companies.manage');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])
            ->middleware('permission:companies.manage');

        Route::get('products', [ProductController::class, 'index'])
            ->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('permission:products.manage');
        Route::get('products/{product}', [ProductController::class, 'show'])
            ->middleware('permission:products.view');
        Route::patch('products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:products.manage');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:products.manage');

        Route::get('pharmacies', [PharmacyController::class, 'index'])
            ->middleware('permission:pharmacies.view');
        Route::post('pharmacies/duplicate-check', [PharmacyController::class, 'duplicateCheck'])
            ->middleware('permission:pharmacies.manage');
        Route::post('pharmacies', [PharmacyController::class, 'store'])
            ->middleware('permission:pharmacies.manage');
        Route::get('pharmacies/{pharmacy}', [PharmacyController::class, 'show'])
            ->middleware('permission:pharmacies.view');
        Route::patch('pharmacies/{pharmacy}', [PharmacyController::class, 'update'])
            ->middleware('permission:pharmacies.manage');
        Route::post('pharmacies/{pharmacy}/suspend', [PharmacyController::class, 'suspend'])
            ->middleware('permission:pharmacies.manage');
        Route::post('pharmacies/{pharmacy}/activate', [PharmacyController::class, 'activate'])
            ->middleware('permission:pharmacies.manage');
        Route::delete('pharmacies/{pharmacy}', [PharmacyController::class, 'destroy'])
            ->middleware('permission:pharmacies.manage');
    });
});

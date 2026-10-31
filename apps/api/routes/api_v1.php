<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CoupleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PortalAccessController;
use App\Http\Controllers\Api\V1\Public\PortalController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RetreatController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:login')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:portal-access', 'portal.token', 'private.headers'])
    ->prefix('public/portal/{token}')
    ->group(function () {
        Route::get('/', [PortalController::class, 'show']);
        Route::get('/announcements', [PortalController::class, 'announcements']);
    });

Route::get('/public/retreats/{retreat}/cover', [RetreatController::class, 'cover'])
    ->middleware('throttle:portal-access')
    ->name('retreats.cover');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('retreats', RetreatController::class)->except(['destroy']);
    Route::apiResource('couples', CoupleController::class)->except(['destroy']);

    Route::apiResource('enrollments', EnrollmentController::class)->except(['destroy']);
    Route::post('/installments/preview', [EnrollmentController::class, 'previewInstallments']);
    Route::patch('/installments/{installment}', [EnrollmentController::class, 'updateInstallment']);
    Route::post('/enrollments/{enrollment}/installments/regenerate', [EnrollmentController::class, 'regenerateInstallments']);
    Route::post('/enrollments/{enrollment}/cancel', [EnrollmentController::class, 'cancel']);

    Route::get('/enrollments/{enrollment}/payments', [PaymentController::class, 'index']);
    Route::post('/enrollments/{enrollment}/payments', [PaymentController::class, 'store']);
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse']);

    Route::get('/enrollments/{enrollment}/access-link', [PortalAccessController::class, 'show']);
    Route::post('/enrollments/{enrollment}/access-link/regenerate', [PortalAccessController::class, 'regenerate']);
    Route::post('/enrollments/{enrollment}/access-link/revoke', [PortalAccessController::class, 'revoke']);

    Route::apiResource('announcements', AnnouncementController::class)->except(['destroy']);
    Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
    Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports/financial', [ReportController::class, 'financial']);
    Route::get('/reports/financial/export', [ReportController::class, 'financialExport']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
});

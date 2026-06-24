<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\LicenseController;
use App\Http\Controllers\Api\Mobile\SyncController;
use App\Http\Controllers\Api\Mobile\ProductController;
use App\Http\Controllers\Api\Mobile\ReportController;
use App\Http\Controllers\Api\Mobile\RegisterController;
use App\Http\Controllers\Api\Mobile\VerifyEmailController;
use App\Http\Controllers\Api\Mobile\ForgotPasswordController;
use App\Http\Controllers\Api\Mobile\ResetPasswordController;
use App\Http\Controllers\Api\Mobile\ChangePasswordController;
use App\Http\Controllers\Api\Mobile\MeController;
use App\Http\Controllers\Api\Mobile\DeviceController;
use App\Http\Controllers\Api\Mobile\UserController;

/*
|--------------------------------------------------------------------------
| FastPos Mobile API Routes
|--------------------------------------------------------------------------
|
| These routes serve the FastPos Mobile app exclusively.
| They are separate from the web routes and the legacy api.php.
|
| Middleware stack:
|   - throttle:120,1    — 120 requests/min per device
|   - auth:api          — Laravel Passport bearer token
|   - mobile.device     — device fingerprint binding check
|
*/

// ─── Public (no auth required) ────────────────────────────────────────────────

Route::prefix('api/mobile')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/register', [RegisterController::class, 'register']);
    Route::get('/auth/verify-email/{token}', [VerifyEmailController::class, 'verify']);
    Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
    Route::post('/auth/reset-password', [ResetPasswordController::class, 'reset']);

    // License activation does not require an auth token
    Route::post('/license/activate', [LicenseController::class, 'activate']);
    Route::get('/license/timestamp',  [LicenseController::class, 'timestamp']);
});

// ─── Authenticated ────────────────────────────────────────────────────────────

Route::middleware(['auth:api', 'throttle:120,1', 'mobile.device', 'mobile.active-subscription'])
    ->prefix('api/mobile')
    ->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [MeController::class, 'show']);
        Route::put('/auth/change-password', [ChangePasswordController::class, 'change']);

        // Devices management
        Route::get('/device', [DeviceController::class, 'index']);
        Route::delete('/device/{id}', [DeviceController::class, 'destroy']);

        // Users & Roles management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::get('/roles', [UserController::class, 'roles']);

        // License (requires valid auth session)
        Route::post('/license/verify',    [LicenseController::class, 'verify']);

        // Sync — Pull (incremental, cursor-based)
        Route::get('/sync/products',       [SyncController::class, 'pullProducts']);
        Route::get('/sync/contacts',       [SyncController::class, 'pullContacts']);
        Route::get('/sync/transactions',   [SyncController::class, 'pullTransactions']);
        Route::get('/sync/reference-data', [SyncController::class, 'pullReferenceData']);
        Route::get('/sync/settings',       [SyncController::class, 'pullSettings']);

        // Sync — Push (offline action queue)
        Route::post('/sync/push', [SyncController::class, 'push']);

        // Products (online search for POS)
        Route::get('/products/search', [ProductController::class, 'search']);
        Route::get('/products/{id}',   [ProductController::class, 'show']);

        // Reports (online only — local SQLite is used when offline)
        Route::get('/reports/sales-summary',  [ReportController::class, 'salesSummary']);
        Route::get('/reports/top-products',   [ReportController::class, 'topProducts']);
        Route::get('/reports/payment-methods',[ReportController::class, 'paymentMethods']);
        Route::get('/reports/daily-sales',    [ReportController::class, 'dailySales']);
        Route::get('/reports/stock-value',    [ReportController::class, 'stockValue']);
    });

<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OcrScanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;

// Redirect home to assets index
Route::get('/', fn() => to_route('assets.index'));

// Localization (i18n)
Route::get('/lang/{locale}', [LanguageController::class, 'switchLang'])->name('lang.switch');

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile Management
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::patch('/profile/notifications', 'updateNotifications')->name('profile.update.notifications');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // Notifications
    Route::controller(NotificationController::class)->name('notifications.')->group(function () {
        Route::post('/notifications/mark-all-read', 'markAllAsRead')->name('markAllAsRead');
        Route::post('/notifications/clear-all', 'clearAll')->name('clearAll');
    });

    // Asset Management
    Route::controller(AssetController::class)->prefix('assets')->name('assets.')->group(function () {
        Route::get('/export', 'export')->name('export');
        Route::get('/{asset}/history', 'history')->name('history');
        Route::get('/{asset}/history/export', 'exportHistory')->name('history.export');
        Route::post('/{asset}/attachments', 'storeAttachment')->name('attachments.store');
        Route::delete('/attachments/{attachment}', 'destroyAttachment')->name('attachments.destroy');
        Route::get('/{asset}/attachments/download/all', 'downloadAllAttachments')->name('attachments.download-all');
    });

    // Smart Import Workflow
    Route::controller(AssetImportController::class)->prefix('assets')->name('assets.')->group(function () {
        Route::post('/import-parse', 'parse')->name('import-parse');
        Route::get('/import-rapid-add', 'rapidAdd')->name('import-rapid-add');
        Route::post('/import-rapid-add', 'storeRapidAdd')->name('import-rapid-add.store');
        Route::get('/import-review', 'review')->name('import-review');
        Route::post('/import-store', 'store')->name('import-store');
        Route::get('/bulk-manual', 'bulkManual')->name('bulk-manual');
    });

    // OCR Analysis
    Route::post('/assets/ocr-scan', [OcrScanController::class, 'scan'])->name('assets.ocr-scan');

    // QR Codes
    Route::get('assets/{asset}/qr', [QrController::class, 'image'])->name('assets.qr');
    Route::view('scan', 'qr.scan')->name('qr.scan');

    // Resources
    Route::resources([
        'assets' => AssetController::class,
        'roles' => RoleController::class,
        'departments' => DepartmentController::class,
        'locations' => LocationController::class,
        'categories' => CategoryController::class,
        'users' => UserController::class,
        'properties' => PropertyController::class,
        'jobs' => JobController::class,
    ]);

    // Tenancy (Property Switching)
    Route::controller(PropertyController::class)->group(function () {
        Route::post('/properties/switch', 'switchProperty')->name('properties.switch');
        Route::get('/select-property', 'selectForm')->name('properties.select.form');
        Route::post('/select-property', 'select')->name('properties.select');
    });

    // Jobs Extended Attributes
    Route::controller(JobController::class)->prefix('jobs')->name('jobs.')->group(function () {
        Route::patch('/{job}/status', 'updateStatus')->name('status');
        Route::post('/{job}/comments', 'addComment')->name('comments');
    });

    // Global Backup/Restore
    Route::controller(BackupController::class)->prefix('backup')->name('backup.')->group(function () {
        Route::post('/download', 'download')->name('download');
        Route::post('/restore', 'restore')->name('restore');
    });
});

// Public Public Signed Resolution
Route::get('/qr/resolve/{uuid}', [QrController::class, 'resolve'])->name('qr.resolve');

// OAuth (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleLoginController::class, 'redirectToGoogle'])->name('login.google');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'handleGoogleCallback']);
});

require __DIR__ . '/auth.php';


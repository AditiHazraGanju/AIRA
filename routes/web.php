<?php

use Illuminate\Support\Facades\Route;
use Modules\HealthTalk\Http\Controllers\ContentLibraryController;
use Modules\HealthTalk\Http\Controllers\GuardrailController;
use Modules\HealthTalk\Http\Controllers\ReportController;
use Modules\HealthTalk\Http\Controllers\StartPitchController;
use Modules\SHUserManagement\Http\Controllers\SHUserManagementController;

/*
|--------------------------------------------------------------------------
| Aira admin panel
|--------------------------------------------------------------------------
| No login: every page is open to whoever can reach this app. Run it on a
| private network, behind a VPN, or behind your web server's or load
| balancer's own authentication. Each page calls the Aira API server to
| server with ADMIN_API_KEY (see App\Services\ApiService).
*/

Route::redirect('/', '/healthtalk/content')->name('index');

// HealthTalk AI
Route::prefix('healthtalk')->name('healthtalk.')->group(function () {

    // Content Library: everything Aira is allowed to answer from
    Route::apiResource('/content', ContentLibraryController::class)->names('content')->except(['show']);
    Route::patch('/content/{id}/toggle-status', [ContentLibraryController::class, 'toggleStatus'])->name('content.toggle-status');

    // Guardrails
    Route::apiResource('/guardrails', GuardrailController::class)->names('guardrails')->except(['show']);
    Route::put('/guardrails-settings', [GuardrailController::class, 'updateSettings'])->name('guardrails.settings.update');

    // Introductory message (Start Pitch)
    Route::get('/start-pitch', [StartPitchController::class, 'index'])->name('start-pitch.index');
    Route::put('/start-pitch', [StartPitchController::class, 'update'])->name('start-pitch.update');
    Route::post('/start-pitch/preview', [StartPitchController::class, 'preview'])->name('start-pitch.preview');

    // Conversation reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
    Route::post('/reports/sessions', [ReportController::class, 'sessionsDatatable'])->name('reports.sessions');
    Route::get('/reports/sessions/{uuid}', [ReportController::class, 'session'])->name('reports.session');
    Route::post('/reports/feedback', [ReportController::class, 'feedbackDatatable'])->name('reports.feedback');
});

// SH Users (the central users table)
Route::prefix('sh-users')->group(function () {
    Route::get('/', [SHUserManagementController::class, 'index'])->name('sh-users.index');
    Route::post('/datatable', [SHUserManagementController::class, 'datatable'])->name('sh-users.datatable');
});

// 404 for everything else
Route::fallback(function () {
    return response()->view('pages.error.404', [], 404);
});

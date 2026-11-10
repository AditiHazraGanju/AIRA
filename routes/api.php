<?php

use App\Http\Controllers\Api\SHUserApiController;
use App\Http\Middleware\AdminApiKey;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Middleware\JwtOrAdminApiKey;
use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\HealthTalk\Http\Controllers\ContentLibraryController;
use Modules\HealthTalk\Http\Controllers\GuardrailController;
use Modules\HealthTalk\Http\Controllers\ReportController;
use Modules\HealthTalk\Http\Controllers\SessionController;
use Modules\HealthTalk\Http\Controllers\StartPitchController;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Three kinds of caller:
|   - Aira users (the Aira web app and healthtalk-relay): a JWT from the OTP
|     login, checked by JwtMiddleware.
|   - The admin panel, server to server: the shared ADMIN_API_KEY in the
|     X-Admin-Key header, checked by AdminApiKey.
|   - Either of the two, for data both sides read (JwtOrAdminApiKey).
*/

Route::prefix('v1')->group(function () {

    // ── Aira login (public) ─────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/send-otp', [AuthController::class, 'sendOtp'])->name('send-otp');
        Route::get('/states', [AuthController::class, 'getAllStates'])->name('app-get-all-states');

        Route::prefix('chatbot')->group(function () {
            Route::post('/verify-otp', [AuthController::class, 'chatbotVerifyOtp'])->name('chatbot-verify-otp');
            Route::post('/register', [AuthController::class, 'chatbotRegisterGuest'])->name('chatbot-register-guest');
        });
    });

    // ── Aira users (JWT) ────────────────────────────────────────────────
    Route::middleware(JwtMiddleware::class)->group(function () {
        // The logged-in user's own profile
        Route::prefix('auth/chatbot')->group(function () {
            Route::get('/profile', [AuthController::class, 'chatbotProfile'])->name('chatbot-profile');
            Route::post('/profile', [AuthController::class, 'chatbotUpdateProfile'])->name('chatbot-update-profile');
        });

        Route::prefix('healthtalk')->group(function () {
            // Content Library search, called by the relay when Aira looks something up
            Route::post('/content/search', [ContentLibraryController::class, 'search'])->name('search-healthtalk-content');

            // Saved conversations: written by the relay, read and rated in the Aira app
            Route::prefix('sessions')->group(function () {
                Route::get('/', [SessionController::class, 'index'])->name('get-healthtalk-sessions');
                Route::post('/', [SessionController::class, 'store'])->name('store-healthtalk-session');
                Route::get('/{uuid}', [SessionController::class, 'show'])->name('get-healthtalk-session');
                Route::post('/{uuid}/messages', [SessionController::class, 'storeMessages'])->name('store-healthtalk-session-messages');
                Route::post('/{uuid}/end', [SessionController::class, 'end'])->name('end-healthtalk-session');
                Route::post('/{uuid}/rating', [SessionController::class, 'rate'])->name('rate-healthtalk-session');
                Route::post('/{uuid}/qa/{qaId}/feedback', [SessionController::class, 'qaFeedback'])->name('healthtalk-session-qa-feedback');
            });
        });
    });

    // ── Read by both Aira (JWT) and the admin panel (admin key) ─────────
    Route::middleware(JwtOrAdminApiKey::class)->prefix('healthtalk')->group(function () {
        Route::get('/guardrails/all', [GuardrailController::class, 'getAllHealthTalkGuardrails'])->name('get-all-healthtalk-guardrails');
        Route::get('/guardrails/settings', [GuardrailController::class, 'getSettings'])->name('get-healthtalk-guardrail-settings');
        Route::get('/start-pitch', [StartPitchController::class, 'index'])->name('get-healthtalk-start-pitch');
    });

    // ── Admin panel only (admin key) ────────────────────────────────────
    Route::middleware(AdminApiKey::class)->group(function () {
        Route::prefix('healthtalk')->group(function () {
            // Content Library
            Route::prefix('content')->group(function () {
                Route::get('/all', [ContentLibraryController::class, 'getAllHealthTalkContent'])->name('get-all-healthtalk-content');
                Route::post('/store', [ContentLibraryController::class, 'store'])->name('store-healthtalk-content');
                Route::post('/update/{id}', [ContentLibraryController::class, 'update'])->name('update-healthtalk-content');
                Route::delete('/delete/{id}', [ContentLibraryController::class, 'destroy'])->name('delete-healthtalk-content');
            });

            // Guardrails
            Route::prefix('guardrails')->group(function () {
                Route::post('/store', [GuardrailController::class, 'store'])->name('store-healthtalk-guardrail');
                Route::put('/update/{id}', [GuardrailController::class, 'update'])->name('update-healthtalk-guardrail');
                Route::delete('/delete/{id}', [GuardrailController::class, 'destroy'])->name('delete-healthtalk-guardrail');
                Route::put('/settings', [GuardrailController::class, 'updateSettings'])->name('update-healthtalk-guardrail-settings');
            });

            // Introductory message (Start Pitch)
            Route::put('/start-pitch', [StartPitchController::class, 'update'])->name('update-healthtalk-start-pitch');
            Route::post('/start-pitch/preview', [StartPitchController::class, 'preview'])->name('preview-healthtalk-start-pitch');

            // Conversation reports (every user's conversations)
            Route::prefix('admin')->group(function () {
                Route::get('/overview', [ReportController::class, 'overview'])->name('get-healthtalk-admin-overview');
                Route::post('/sessions/datatable', [ReportController::class, 'sessionsDatatable'])->name('get-healthtalk-admin-sessions-datatable');
                Route::get('/sessions/{uuid}', [ReportController::class, 'session'])->name('get-healthtalk-admin-session');
                Route::post('/feedback/datatable', [ReportController::class, 'feedbackDatatable'])->name('get-healthtalk-admin-feedback-datatable');
            });
        });

        // SH Users (the central users table)
        Route::post('/sh-users/datatable', [SHUserApiController::class, 'getDatatableData'])->name('api.get-sh-users-datatable');
    });
});

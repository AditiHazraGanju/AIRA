<?php

use Illuminate\Support\Facades\Route;

// This admin app doesn't expose its own API guard/routes for HealthTalk - the actual
// chatbot API lives in saathi-laravel-api. This file is left unused, same as the
// module's routes/web.php (see the note there).

// Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
//     Route::apiResource('healthtalks', HealthTalkController::class)->names('healthtalk');
// });

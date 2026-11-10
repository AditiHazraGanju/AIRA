<?php

/**
 * Aira API endpoints the admin panel calls, by name.
 * Used by App\Services\ApiService::call('<name>', ...). Paths are relative to
 * AIRA_API_URL. All of them need the admin key (X-Admin-Key header).
 */

return [
    //// SH Users //-----------------------------------------------------------//

    // SH Users (the central users table) for the server-side DataTable
    'get-sh-users-datatable' => [
        'endpoint' => '/api/v1/sh-users/datatable',
        'method' => 'POST',
    ],

    //// HealthTalk AI - Content Library //------------------------------------//

    // Get All Content Library items
    'get-all-healthtalk-content' => [
        'endpoint' => '/api/v1/healthtalk/content/all',
        'method' => 'GET',
    ],

    // Store Content Library item (text or PDF upload)
    'store-healthtalk-content' => [
        'endpoint' => '/api/v1/healthtalk/content/store',
        'method' => 'POST',
    ],

    // Update Content Library item
    'update-healthtalk-content' => [
        'endpoint' => '/api/v1/healthtalk/content/update/{id}',
        'method' => 'POST', // POST (not PUT) because file uploads need multipart on update too
    ],

    // Delete Content Library item
    'delete-healthtalk-content' => [
        'endpoint' => '/api/v1/healthtalk/content/delete/{id}',
        'method' => 'DELETE',
    ],


    //// HealthTalk AI - Guardrail Routes //-----------------------------------------------------//

    // Get All Guardrail rules
    'get-all-healthtalk-guardrails' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/all',
        'method' => 'GET',
    ],

    // Store Guardrail rule
    'store-healthtalk-guardrail' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/store',
        'method' => 'POST',
    ],

    // Update Guardrail rule
    'update-healthtalk-guardrail' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/update/{id}',
        'method' => 'PUT',
    ],

    // Delete Guardrail rule
    'delete-healthtalk-guardrail' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/delete/{id}',
        'method' => 'DELETE',
    ],

    // Get the default "guardrail hit" message, per language
    'get-healthtalk-guardrail-settings' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/settings',
        'method' => 'GET',
    ],

    // Update the default "guardrail hit" message, per language
    'update-healthtalk-guardrail-settings' => [
        'endpoint' => '/api/v1/healthtalk/guardrails/settings',
        'method' => 'PUT',
    ],


    //// HealthTalk AI - Introductory Message (Start Pitch) Routes //-----------------------------------------------------//

    // Get the saved Introductory Message text + generated audio, per language
    'get-healthtalk-start-pitch' => [
        'endpoint' => '/api/v1/healthtalk/start-pitch',
        'method' => 'GET',
    ],

    // Save the Introductory Message text and (re)generate its audio, per language
    'update-healthtalk-start-pitch' => [
        'endpoint' => '/api/v1/healthtalk/start-pitch',
        'method' => 'PUT',
    ],

    // Generate a one-off preview clip for unsaved text
    'preview-healthtalk-start-pitch' => [
        'endpoint' => '/api/v1/healthtalk/start-pitch/preview',
        'method' => 'POST',
    ],

    // HealthTalk AI - Conversation Reports (API side: AdminApiKey middleware)
    'get-healthtalk-admin-overview' => [
        'endpoint' => '/api/v1/healthtalk/admin/overview',
        'method' => 'GET',
    ],
    'get-healthtalk-admin-sessions-datatable' => [
        'endpoint' => '/api/v1/healthtalk/admin/sessions/datatable',
        'method' => 'POST',
    ],
    'get-healthtalk-admin-session' => [
        'endpoint' => '/api/v1/healthtalk/admin/sessions/{uuid}',
        'method' => 'GET',
    ],
    'get-healthtalk-admin-feedback-datatable' => [
        'endpoint' => '/api/v1/healthtalk/admin/feedback/datatable',
        'method' => 'POST',
    ],
];

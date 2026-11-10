<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Shared secret the admin panel sends (X-Admin-Key header) on every call to
    // the admin endpoints. Checked by App\Http\Middleware\AdminApiKey. The admin
    // panel's ADMIN_API_KEY must hold the same value. Empty = admin endpoints
    // reject every request.
    'admin' => [
        'api_key' => env('ADMIN_API_KEY'),
    ],

    // Login OTPs (App\Services\MessageService).
    'smsgatewayhub' => [
        'sms_url' => env('SMSGATEWAYHUB_SMS_URL', 'https://www.smsgatewayhub.com/api/mt/SendSMS'),
        'sms_api_key' => env('SMSGATEWAYHUB_SMS_API_KEY'),
        'sender_id' => env('SMSGATEWAYHUB_SENDER_ID'),
        'whatsapp_url' => env('SMSGATEWAYHUB_MESSAGE_URL'),
        'whatsapp_api_key' => env('SMSGATEWAYHUB_DEV_API_KEY'),
        'whatsapp_otp_template' => env('SMSGATEWAYHUB_WHATSAPP_OTP_TEMPLATE', 'saathi_otp'),
    ],

    // HealthTalk Content Library indexing + search (Vertex AI).
    // asia-south1 serves both models (verified), keeping content in India.
    'vertex' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'location' => env('VERTEX_LOCATION', 'asia-south1'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        // Must match the relay's EMBEDDING_MODEL (the search endpoint rejects
        // query vectors from any other model), and the dimensions must match
        // ht_content_chunks.embedding vector(768). Changing either means
        // re-indexing: php artisan healthtalk:index-content --all
        'embedding_model' => env('VERTEX_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'embedding_dimensions' => 768,
        'extraction_model' => env('VERTEX_EXTRACTION_MODEL', 'gemini-2.5-flash'),
    ],

];

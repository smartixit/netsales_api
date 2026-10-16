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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'smsgate' => [
        'server_type' => env('SMSGATE_SERVER_TYPE', 'cloud'), // cloud, local, private
        'base_url' => env('SMSGATE_BASE_URL', 'https://api.sms-gate.app'),
        'auth_type' => env('SMSGATE_AUTH_TYPE', 'bearer'), // bearer, basic
        'api_token' => env('SMSGATE_API_TOKEN'),
        'refresh_token' => env('SMSGATE_REFRESH_TOKEN'),
        'token_expires_at' => env('SMSGATE_TOKEN_EXPIRES_AT'),
        'username' => env('SMSGATE_USERNAME'),
        'password' => env('SMSGATE_PASSWORD'),
        'device_id' => env('SMSGATE_DEVICE_ID'),
        'sim_number' => (int) env('SMSGATE_SIM_NUMBER', 1),
        'skip_phone_validation' => (bool) env('SMSGATE_SKIP_PHONE_VALIDATION', false),
        'device_active_within' => (int) env('SMSGATE_DEVICE_ACTIVE_WITHIN', 0),
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI', 'http://localhost'),
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'application_name' => 'Qayd Backup',
        'folder_name' => env('GOOGLE_DRIVE_FOLDER_NAME', 'Qayd Backup'),
    ],

    'firebase' => [
        'is_enabled' => (bool) env('FIREBASE_ENABLED', true),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
    ],

];


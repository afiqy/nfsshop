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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', '/auth/microsoft/callback'),
        'tenant' => env('MICROSOFT_TENANT', 'common'),
    ],

    'stripe' => [
        'sandbox_enabled' => env('STRIPE_SANDBOX_ENABLED', false),
        'test_secret' => env('STRIPE_TEST_SECRET'),
    ],

    'toyyibpay' => [
        'sandbox_enabled' => env('TOYYIBPAY_SANDBOX_ENABLED', false),
        'test_secret' => env('TOYYIBPAY_TEST_SECRET'),
        'test_category' => env('TOYYIBPAY_TEST_CATEGORY'),
    ],

    'billplz' => [
        'sandbox_enabled' => env('BILLPLZ_SANDBOX_ENABLED', false),
        'test_secret' => env('BILLPLZ_TEST_SECRET'),
        'test_collection' => env('BILLPLZ_TEST_COLLECTION'),
        'test_signature_key' => env('BILLPLZ_TEST_SIGNATURE_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];

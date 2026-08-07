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

    /**
     * ResellerClub — domain availability and pricing.
     *
     * Left blank the domain page falls back to asking people to get in touch, so the site works
     * with or without an account rather than showing a broken search.
     */
    'resellerclub' => [
        'user_id' => env('RESELLERCLUB_USER_ID'),
        'api_key' => env('RESELLERCLUB_API_KEY'),
        // Their sandbox host. Same calls, no money, no real registrations.
        'demo' => env('RESELLERCLUB_DEMO', false),
        // Testing hook — leave unset to use their live or sandbox host.
        'base_url' => env('RESELLERCLUB_BASE_URL'),
        'currency' => env('RESELLERCLUB_CURRENCY', 'USD'),
        // Comma-separated; blank falls back to their OrderBox DNS, free with every registration.
        'nameservers' => env('RESELLERCLUB_NS'),
        'tlds' => env('RESELLERCLUB_TLDS', 'com,net,org,co,io,shop,xyz,info,biz,online,store,dev'),
    ],

    /** OpenAI — drafts the WhatsApp auto-replies. Model is a string so switching is one line. */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        // Testing hook — leave unset for the real API. Server config only.
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
    ],

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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Shared secret for the bounce/complaint webhook. Unset means the endpoint stays closed.
    'email_webhook' => [
        'secret' => env('EMAIL_WEBHOOK_SECRET'),
    ],

];

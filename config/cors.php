<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    // Frontend (Nuxt) origins that may call the API from the browser (client-side navigation).
    // SSR (server→server) doesn't need CORS, but client-side fetches do — so the LIVE domain
    // MUST be here, otherwise product pages 404 on navigation and only work after a reload.
    'allowed_origins' => array_values(array_filter([
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        // 3100 = local verification builds (preview/testing).
        'http://localhost:3100',
        'http://127.0.0.1:3100',
        'http://localhost:4000',
        'http://127.0.0.1:4000',
        // Production storefront.
        'https://razinsoft.com',
        'https://www.razinsoft.com',
        env('FRONTEND_URL'),
    ])),

    // Also allow any razinsoft.com subdomain (staging, previews) over https.
    'allowed_origins_patterns' => ['#^https://([a-z0-9-]+\.)?razinsoft\.com$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Token-based (Bearer) auth — cookies not required
    'supports_credentials' => false,
];

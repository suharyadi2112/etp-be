<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // 'allowed_methods' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    // 'allowed_origins' => ['*'],
    'allowed_origins' => ['http://localhost:8080','http://143.198.218.47:8085/'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    // 'allowed_headers' => ['Content-Type'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

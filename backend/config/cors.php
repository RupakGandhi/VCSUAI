<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The frontend (index.html) is a standalone file deployed independently
    | of this backend, and where the client ultimately hosts it isn't
    | decided yet -- so this allows any origin rather than pinning to one.
    | This is safe here specifically because the API is public, read-only,
    | and unauthenticated (no cookies/sessions -- supports_credentials is
    | false below), so there's no session/credential leakage risk from
    | opening it up broadly.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_headers' => ['Content-Type', 'Accept'],

    'exposed_headers' => ['ETag'],

    'max_age' => 0,

    'supports_credentials' => false,

];

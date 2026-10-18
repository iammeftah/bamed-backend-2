<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma separated list in .env, e.g. http://localhost:3000,https://bamed.ma
    'allowed_origins' => array_filter(
        explode(',', env('FRONTEND_URL', 'http://localhost:3000'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer tokens only, no cookies
    'supports_credentials' => false,

];
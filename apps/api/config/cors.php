<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // Set WEB_ORIGIN in .env to your site, e.g. https://markd.ng. Several origins: separate with commas.
    'allowed_origins' => array_map('trim', explode(',', env('WEB_ORIGIN', 'http://localhost:3000'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];

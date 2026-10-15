<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'Content-Type', 'X-Requested-With'],

    'max_age' => 86400, // 24 hours (يقلل من طلبات التحقق المسبق - Preflight)

    'supports_credentials' => false,

];

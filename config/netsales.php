<?php

return [
    'platform_admin' => [
        'phone' => env('NETSALES_PLATFORM_ADMIN_PHONE'),
        'password' => env('NETSALES_PLATFORM_ADMIN_PASSWORD'),
        'name' => env('NETSALES_PLATFORM_ADMIN_NAME', 'Platform Administrator'),
        'email' => env('NETSALES_PLATFORM_ADMIN_EMAIL'),
    ],
];

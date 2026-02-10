<?php

require_once __DIR__ . '/../helpers/Env.php';
Env::load();

return [
    'jwt_secret' => Env::get('JWT_SECRET', 'CHANGE-THIS-IN-PRODUCTION'),
    'jwt_expiry' => (int) Env::get('JWT_EXPIRY', '86400'), // 24 hours
    'cors_origin' => Env::get('CORS_ORIGIN', '*'),
    'app_name' => 'SmartRecur',
];

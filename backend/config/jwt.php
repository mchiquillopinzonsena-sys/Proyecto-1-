<?php
/**
 * JWT Configuration
 */

return [
    'secret' => getenv('JWT_SECRET'),
    'expiry' => (int)getenv('JWT_EXPIRY', 3600),
    'refresh_expiry' => (int)getenv('JWT_REFRESH_EXPIRY', 604800),
    'algorithm' => 'HS256',
    'issuer' => 'intermica.sas',
    'audience' => 'intermica-app',
];

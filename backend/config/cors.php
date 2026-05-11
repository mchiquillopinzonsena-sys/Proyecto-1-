<?php
/**
 * CORS Configuration
 */

return [
    'allowed_origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    'allowed_methods' => explode(',', getenv('CORS_ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS')),
    'allowed_headers' => explode(',', getenv('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization')),
    'max_age' => 3600,
    'supports_credentials' => true,
];

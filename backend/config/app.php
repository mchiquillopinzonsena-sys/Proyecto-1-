<?php
/**
 * Application Configuration
 */

return [
    'name' => getenv('APP_NAME', 'Intérmica API'),
    'version' => getenv('APP_VERSION', '1.0.0'),
    'env' => getenv('APP_ENV', 'production'),
    'debug' => getenv('APP_DEBUG', false),
    'log_level' => getenv('APP_LOG_LEVEL', 'info'),
    'timezone' => 'America/Bogota',
    'max_upload_size' => (int)getenv('MAX_UPLOAD_SIZE', 52428800),
    'allowed_extensions' => explode(',', getenv('ALLOWED_EXTENSIONS', 'pdf,jpg,jpeg,png,docx')),
];

<?php

date_default_timezone_set('Asia/Jakarta');

$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$is_local = (
    $http_host === 'localhost'
    || $http_host === '127.0.0.1'
    || (strpos($http_host, '192.168.') === 0)
    || (strpos($http_host, '10.') === 0)
    || (strpos($http_host, '.test') !== false)
    || (strpos($http_host, '.localhost') !== false)
    || (strpos($http_host, 'localhost:') !== false)
);

if ($is_local) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    if (defined('LOG_PATH') && is_dir(LOG_PATH) && is_writable(LOG_PATH)) {
        ini_set('error_log', LOG_PATH . '/error.log');
    }
}

// Google OAuth Client ID
define('GOOGLE_CLIENT_ID', '101348972853-nnnrbi4a2pk8gavkjtuoifn9m5cq43o9.apps.googleusercontent.com');

<?php
$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($path);
if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$publicRoot = realpath(__DIR__ . '/../public');
$relative = $path === '/' ? '' : ltrim($path, '/');
$ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
$blocked = ['php', 'phtml', 'phar', 'env', 'ini', 'sql', 'htaccess'];

if ($publicRoot && $relative !== '' && !in_array($ext, $blocked, true)) {
    $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
    $rootPrefix = rtrim($publicRoot, '\\/') . DIRECTORY_SEPARATOR;
    if ($candidate && is_file($candidate) && strpos($candidate, $rootPrefix) === 0) {
        $types = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'mjs' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'map' => 'application/json',
            'json' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'pdf' => 'application/pdf',
        ];
        if (isset($types[$ext])) {
            header('Content-Type: ' . $types[$ext]);
            header('Cache-Control: public, max-age=86400');
            header('X-Content-Type-Options: nosniff');
            readfile($candidate);
            exit;
        }
    }
}

require __DIR__ . '/../public/index.php';

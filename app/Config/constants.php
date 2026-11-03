<?php
/**
 * Konstant aplikasi Sistem Laporan ASN
 * Definisi path, nama, dan konstanta global.
 */

// Path dasar project
define('BASE_PATH', dirname(__DIR__, 2));

// Path web root (untuk asset)
define('BASE_URL', '');

// Path storage
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', BASE_PATH . '/public/uploads');
define('UPLOAD_BUKTI_PATH', UPLOAD_PATH . '/bukti');
define('UPLOAD_LAPORAN_PATH', UPLOAD_PATH . '/laporan');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('GENERATED_PATH', STORAGE_PATH . '/output-word');
define('TEMPLATE_PATH', STORAGE_PATH . '/templates');

// Path views & app
define('VIEWS_PATH', BASE_PATH . '/app/Views');
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/app/Config');
define('PUBLIC_PATH', BASE_PATH . '/public');

// Path asset (relatif ke web root = public/)
define('ASSET_URL', BASE_URL . '/assets');
define('CSS_URL', ASSET_URL . '/css');
define('JS_URL', ASSET_URL . '/js');
define('IMG_URL', ASSET_URL . '/images');

// Nama aplikasi
define('APP_NAME', 'Sistem Laporan ASN');
define('APP_SUBTITLE', 'Pelaporan Kinerja Harian');
define('APP_VERSION', '2.0.0');

// Batas upload
define('MAX_UPLOAD_FILES', 5);
define('MAX_UPLOAD_SIZE', 5242880); // 5 MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png']);

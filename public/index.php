<?php
require_once dirname(__DIR__) . '/app/helpers/session.php';

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$requestedFile = basename($path);
if ($requestedFile === '' || strpos($requestedFile, '.php') === false) {
    $requestedFile = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}
if ($requestedFile === '') {
    $requestedFile = 'index.php';
}

$routes = [
    'dashboard.php' => dirname(__DIR__) . '/app/Pages/dashboard.php',
    'edit_laporan.php' => dirname(__DIR__) . '/app/Pages/edit_laporan.php',
    'laporan_harian.php' => dirname(__DIR__) . '/app/Pages/laporan_harian.php',
    'login.php' => dirname(__DIR__) . '/app/Pages/login.php',
    'logout.php' => dirname(__DIR__) . '/app/Pages/logout.php',
    'pengaturan.php' => dirname(__DIR__) . '/app/Pages/pengaturan.php',
    'perjadin.php' => dirname(__DIR__) . '/app/Pages/perjadin.php',
    'profil.php' => dirname(__DIR__) . '/app/Pages/profil.php',
    'rekap_bulanan.php' => dirname(__DIR__) . '/app/Pages/rekap_bulanan.php',
    'rhk.php' => dirname(__DIR__) . '/app/Pages/rhk.php',
    'ajax_rhk_options.php' => dirname(__DIR__) . '/app/Handlers/ajax_rhk_options.php',
    'get_bukti.php' => dirname(__DIR__) . '/app/Handlers/get_bukti.php',
    'hapus_bukti.php' => dirname(__DIR__) . '/app/Handlers/hapus_bukti.php',
    'hapus_laporan.php' => dirname(__DIR__) . '/app/Handlers/hapus_laporan.php',
    'simpan_laporan.php' => dirname(__DIR__) . '/app/Handlers/simpan_laporan.php',
    'cetak_laporan_harian.php' => dirname(__DIR__) . '/app/Reports/cetak_laporan_harian.php',
    'cetak_pdf.php' => dirname(__DIR__) . '/app/Reports/cetak_pdf.php',
    'isi_dari_format_word.php' => dirname(__DIR__) . '/app/Reports/isi_dari_format_word.php',
];

if ($requestedFile === 'index.php') {
    if (is_logged_in()) {
        header('Location: dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

if (!isset($routes[$requestedFile])) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

require_once $routes[$requestedFile];

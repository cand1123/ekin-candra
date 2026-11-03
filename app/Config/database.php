<?php
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
    $host     = 'localhost';
    $username = 'root';
    $password = ''; // Default Laragon biasanya kosong
    $dbname   = 'laporan_asn'; // Diubah sesuai nama database lokal Anda
} else {
    $host     = 'sql301.infinityfree.com';
    $username = 'if0_43120923';
    $password = 'enYc5LsCcWOGrZ7';
    $dbname   = 'if0_43120923_asn';
}

try {
    $mysqli = new mysqli($host, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        die("Koneksi gagal: " . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
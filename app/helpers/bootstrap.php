<?php
/**
 * Bootstrap Sistem Laporan ASN
 * Memuat semua konfigurasi, database, session, dan helpers.
 * File ini di-include di setiap entry point.
 */

require_once __DIR__ . '/../Config/constants.php';
require_once __DIR__ . '/../Config/app.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/session.php';

// Helper: tanggal format Indonesia
if (!function_exists('tanggal_id')) {
    function tanggal_id($tgl) {
        $dt = DateTime::createFromFormat('Y-m-d', $tgl);
        if (!$dt) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $tgl);
        }
        if (!$dt) {
            try { $dt = new DateTime($tgl); } catch (Exception $e) { return ''; }
        }
        $arrBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                     'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return (int)$dt->format('d') . ' ' . $arrBulan[(int)$dt->format('m')];
    }
}

// Helper: nama hari Indonesia
if (!function_exists('nama_hari')) {
    function nama_hari($date) {
        $arrHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $dt = is_string($date) ? new DateTime($date) : $date;
        return $arrHari[(int)$dt->format('w')];
    }
}

// Helper: format tanggal lengkap Indonesia
if (!function_exists('tanggal_lengkap')) {
    function tanggal_lengkap($date) {
        $dt = is_string($date) ? new DateTime($date) : $date;
        return nama_hari($dt) . ', ' . tanggal_id($dt->format('Y-m-d')) . ' ' . $dt->format('Y');
    }
}

// Helper: generate inisial dari nama
if (!function_exists('get_inisial')) {
    function get_inisial($nama) {
        $inisial = '';
        foreach (explode(' ', trim($nama)) as $w) {
            if ($w !== '') { $inisial .= mb_strtoupper(mb_substr($w, 0, 1)); }
            if (strlen($inisial) >= 2) break;
        }
        return $inisial ?: 'U';
    }
}

// Helper: sanitize output
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

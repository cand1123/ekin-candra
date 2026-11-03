<?php
/**
 * Koneksi PostgreSQL Supabase.
 * Halaman lama tetap memakai $mysqli; kelas pembungkus ada di pg_compat.php.
 */

require_once __DIR__ . '/pg_compat.php';
require_once dirname(__DIR__) . '/helpers/storage.php';

function asn_load_dotenv($path)
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }
        $len = strlen($value);
        if ($len >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && $value[$len - 1] === $quote) {
                $value = substr($value, 1, -1);
            }
        }
        $existing = getenv($key);
        if ($existing === false || $existing === '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function asn_conn_escape($value)
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string)$value) . "'";
}

function asn_parse_database_url($url)
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return null;
    }
    $name = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    return [
        'host' => $parts['host'],
        'port' => isset($parts['port']) ? (int)$parts['port'] : 5432,
        'database' => $name !== '' ? $name : 'postgres',
        'username' => isset($parts['user']) ? rawurldecode($parts['user']) : 'postgres',
        'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
        'sslmode' => $query['sslmode'] ?? 'require',
    ];
}

function asn_db_config()
{
    static $loaded = false;
    if (!$loaded) {
        asn_load_dotenv(dirname(__DIR__, 2) . '/.env');
        $loaded = true;
    }

    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        $url = getenv('SUPABASE_DB_URL') ?: '';
    }
    if ($url !== '') {
        $parsed = asn_parse_database_url($url);
        if ($parsed && $parsed['host'] !== '' && $parsed['password'] !== '') {
            return $parsed;
        }
    }

    $host = getenv('DB_HOST') ?: '';
    $user = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: '');
    $pass = getenv('DB_PASSWORD') ?: '';
    $name = getenv('DB_DATABASE') ?: 'postgres';
    $port = (int)(getenv('DB_PORT') ?: 5432);
    $ssl = getenv('DB_SSLMODE') ?: 'require';
    $placeholders = ['xxxxxx', 'password_anda', 'password_database_supabase_anda'];
    if ($host === '' || $user === '' || $pass === '' || $name === '') {
        return null;
    }
    foreach ($placeholders as $placeholder) {
        if (strpos($host, $placeholder) !== false || $pass === $placeholder) {
            return null;
        }
    }
    return [
        'host' => $host,
        'port' => $port > 0 ? $port : 5432,
        'database' => $name,
        'username' => $user,
        'password' => $pass,
        'sslmode' => $ssl !== '' ? $ssl : 'require',
    ];
}

function asn_pg_connect_error($value = null)
{
    static $error = '';
    if (func_num_args() > 0) {
        $error = trim((string)$value);
    }
    return $error;
}

function asn_pg_connect()
{
    static $db = null;
    static $failed = false;
    if ($db) {
        return $db;
    }
    if ($failed) {
        return false;
    }
    if (!function_exists('pg_connect')) {
        $failed = true;
        return false;
    }
    $cfg = asn_db_config();
    if (!$cfg) {
        $failed = true;
        return false;
    }
    $conn = 'host=' . asn_conn_escape($cfg['host'])
        . ' port=' . (int)$cfg['port']
        . ' dbname=' . asn_conn_escape($cfg['database'])
        . ' user=' . asn_conn_escape($cfg['username'])
        . ' password=' . asn_conn_escape($cfg['password'])
        . ' sslmode=' . asn_conn_escape($cfg['sslmode'])
        . ' connect_timeout=10'
        . ' options=' . asn_conn_escape('-c search_path=public');
    $link = @pg_connect($conn);
    if (!$link) {
        $failed = true;
        $last = error_get_last();
        $message = is_array($last) && isset($last['message']) ? $last['message'] : '';
        asn_pg_connect_error($message);
        return false;
    }
    @pg_query($link, "SET TIME ZONE 'Asia/Jakarta'");
    $db = $link;
    return $db;
}

function asn_db_setup_message($detail)
{
    $hint = 'Isi DATABASE_URL, atau DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE, dan DB_PORT. '
        . 'Untuk Laragon, isi file .env di folder proyek. Untuk Vercel, isi Environment Variables lalu deploy ulang. '
        . 'Pakai Session pooler Supabase, bukan host db.xxx.supabase.co: '
        . 'host aws-0-REGION.pooler.supabase.com, user postgres.PROJECT_REF, port 5432, sslmode=require.';
    if ($detail !== '') {
        if (strpos($detail, 'db.') !== false) {
            $hint .= ' Koneksi langsung db.*.supabase.co biasanya hanya IPv6 dan ditolak dari Vercel.';
        }
        return $hint . ' Detail: ' . $detail;
    }
    return $hint;
}

if (defined('ASN_DB_NO_CONNECT')) {
    return;
}

$db = asn_pg_connect();
if (!$db) {
    if (!function_exists('pg_connect')) {
        $detail = 'Ekstensi PHP pgsql tidak aktif.';
    } elseif (!asn_db_config()) {
        $detail = '';
    } else {
        $detail = asn_pg_connect_error();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Koneksi database Supabase gagal.\n" . asn_db_setup_message($detail);
    exit;
}

@pg_query($db, 'CREATE TABLE IF NOT EXISTS public.php_sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
)');
@pg_query($db, 'CREATE INDEX IF NOT EXISTS idx_php_sessions_expires ON public.php_sessions (expires_at)');

$mysqli = new AsnMysqli($db);

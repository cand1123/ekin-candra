<?php
/**
 * Menyimpan unggahan ke disk lokal bila folder bisa ditulis,
 * atau ke Supabase Storage saat berjalan di Vercel.
 */

function asn_storage_is_local()
{
    if (getenv('VERCEL') === '1' || (isset($_SERVER['VERCEL']) && (string)$_SERVER['VERCEL'] === '1')) {
        return false;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return $host === ''
        || $host === 'localhost'
        || strpos($host, 'localhost:') === 0
        || strpos($host, '127.0.0.1') === 0
        || strpos($host, '192.168.') === 0
        || strpos($host, '10.') === 0
        || substr($host, -5) === '.test'
        || substr($host, -10) === '.localhost';
}

function asn_supabase_secret_key()
{
    foreach (['SUPABASE_SECRET_KEY', 'SUPABASE_SERVICE_ROLE_KEY'] as $name) {
        $key = getenv($name);
        if (!is_string($key)) {
            continue;
        }
        $key = trim($key);
        if ($key === '' || strncmp($key, 'sb_publishable_', 16) === 0) {
            continue;
        }
        return $key;
    }
    return '';
}

function asn_supabase_key()
{
    return asn_supabase_secret_key();
}

function asn_supabase_url()
{
    $url = getenv('SUPABASE_URL') ?: '';
    return rtrim($url, '/');
}

function asn_storage_bucket()
{
    $bucket = getenv('SUPABASE_STORAGE_BUCKET') ?: 'bukti';
    return $bucket !== '' ? $bucket : 'bukti';
}

function asn_supabase_configured()
{
    return asn_supabase_url() !== '' && asn_supabase_key() !== '';
}

function asn_use_remote_storage()
{
    if (!asn_supabase_configured()) {
        return false;
    }
    $probe = dirname(__DIR__, 2) . '/public/uploads/bukti';
    return !is_dir($probe) || !is_writable($probe);
}

function asn_curl_apply_tls($ch)
{
    if (asn_storage_is_local()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        return;
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
}

function asn_supabase_request($method, $path, $body, $contentType, $extraHeaders = [])
{
    $url = asn_supabase_url() . $path;
    $key = asn_supabase_secret_key();
    if ($key === '') {
        asn_storage_last_error('SUPABASE_SECRET_KEY tidak valid. Isi secret key (sb_secret_...), bukan publishable key.');
        return [0, ''];
    }

    $headers = [
        'Authorization: Bearer ' . $key,
        'apikey: ' . $key,
    ];
    if ($contentType) {
        $headers[] = 'Content-Type: ' . $contentType;
    }
    foreach ($extraHeaders as $header) {
        $headers[] = $header;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        asn_storage_last_error('cURL tidak dapat diinisialisasi.');
        return [0, ''];
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    asn_curl_apply_tls($ch);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        $detail = $error !== '' ? $error : 'koneksi terputus sebelum ada respons';
        asn_storage_last_error('Koneksi ke penyimpanan foto gagal (HTTP 0 / cURL ' . $errno . '): ' . $detail);
        return [0, ''];
    }

    return [$code, $response];
}

function asn_storage_last_error($value = null)
{
    static $error = '';
    if (func_num_args() > 0) {
        $error = trim((string)$value);
    }
    return $error;
}

function asn_storage_error_text($response, $code)
{
    $data = json_decode((string)$response, true);
    $message = is_array($data) ? (string)($data['message'] ?? $data['error'] ?? '') : '';
    $message = trim(preg_replace('/\s+/', ' ', $message));
    if ((int)$code === 0) {
        $stored = asn_storage_last_error();
        if ($stored !== '') {
            return $stored;
        }
        return 'Koneksi ke penyimpanan foto gagal (HTTP 0). Periksa jaringan atau sertifikat SSL Laragon.';
    }
    if ($message === '') {
        return 'Penyimpanan foto menolak unggahan (HTTP ' . (int)$code . ').';
    }
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    return $message;
}

function asn_storage_bucket_id()
{
    $bucket = asn_storage_bucket();
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $bucket)) {
        return 'bukti';
    }
    return $bucket;
}

function asn_storage_ensure_via_sql()
{
    if (!function_exists('asn_pg_connect')) {
        return false;
    }
    $db = asn_pg_connect();
    if (!$db) {
        asn_storage_last_error('Koneksi database untuk menyiapkan penyimpanan foto tidak tersedia.');
        return false;
    }

    $bucket = asn_storage_bucket_id();
    $literal = "'" . str_replace("'", "''", $bucket) . "'";
    $inserted = @pg_query(
        $db,
        "INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
         VALUES ({$literal}, {$literal}, true, 5242880, ARRAY['image/jpeg','image/png']::text[])
         ON CONFLICT (id) DO UPDATE
         SET public = true"
    );
    if ($inserted === false) {
        asn_storage_last_error('Bucket penyimpanan foto tidak dapat disiapkan.');
        return false;
    }

    $slug = preg_replace('/[^a-z0-9_]/', '_', $bucket);
    $policies = [
        'asn_' . $slug . '_select' => "CREATE POLICY asn_{$slug}_select ON storage.objects FOR SELECT TO public USING (bucket_id = {$literal})",
        'asn_' . $slug . '_insert' => "CREATE POLICY asn_{$slug}_insert ON storage.objects FOR INSERT TO anon, authenticated WITH CHECK (bucket_id = {$literal})",
        'asn_' . $slug . '_update' => "CREATE POLICY asn_{$slug}_update ON storage.objects FOR UPDATE TO anon, authenticated USING (bucket_id = {$literal}) WITH CHECK (bucket_id = {$literal})",
        'asn_' . $slug . '_delete' => "CREATE POLICY asn_{$slug}_delete ON storage.objects FOR DELETE TO anon, authenticated USING (bucket_id = {$literal})",
    ];
    foreach ($policies as $name => $sql) {
        $exists = @pg_query_params(
            $db,
            "SELECT 1
             FROM pg_policy p
             JOIN pg_class c ON c.oid = p.polrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'storage' AND c.relname = 'objects' AND p.polname = $1",
            [$name]
        );
        if ($exists && pg_num_rows($exists) > 0) {
            continue;
        }
        if (@pg_query($db, $sql) === false) {
            asn_storage_last_error('Izin penyimpanan foto tidak dapat disiapkan.');
            return false;
        }
    }
    return true;
}

function asn_supabase_ensure_bucket()
{
    static $ready = false;
    if ($ready) {
        return true;
    }
    $bucket = asn_storage_bucket_id();
    [$code] = asn_supabase_request(
        'POST',
        '/storage/v1/bucket',
        json_encode([
            'id' => $bucket,
            'name' => $bucket,
            'public' => true,
            'file_size_limit' => 5242880,
            'allowed_mime_types' => ['image/jpeg', 'image/png'],
        ]),
        'application/json'
    );
    if (($code >= 200 && $code < 300) || $code === 409) {
        $ready = true;
        return true;
    }
    $ready = asn_storage_ensure_via_sql();
    return $ready;
}

function asn_encode_object_path($relativePath)
{
    $parts = explode('/', str_replace('\\', '/', ltrim($relativePath, '/')));
    $encoded = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $encoded[] = rawurlencode($part);
    }
    return implode('/', $encoded);
}

function asn_store_upload($tmpPath, $relativePath, $mime)
{
    $relativePath = str_replace('\\', '/', ltrim((string)$relativePath, '/'));
    if ($relativePath === '' || !is_file($tmpPath)) {
        return false;
    }

    if (asn_use_remote_storage()) {
        asn_storage_last_error('');
        asn_supabase_ensure_bucket();
        $bucket = asn_storage_bucket_id();
        $object = asn_encode_object_path($relativePath);
        $body = file_get_contents($tmpPath);
        if ($body === false) {
            asn_storage_last_error('Berkas sementara tidak dapat dibaca.');
            return false;
        }
        [$code, $response] = asn_supabase_request(
            'POST',
            '/storage/v1/object/' . rawurlencode($bucket) . '/' . $object,
            $body,
            $mime ?: 'application/octet-stream',
            ['x-upsert: true']
        );
        if ($code === 404 || $code === 400) {
            asn_supabase_ensure_bucket();
            [$code, $response] = asn_supabase_request(
                'POST',
                '/storage/v1/object/' . rawurlencode($bucket) . '/' . $object,
                $body,
                $mime ?: 'application/octet-stream',
                ['x-upsert: true']
            );
        }
        if ($code < 200 || $code >= 300) {
            asn_storage_last_error(asn_storage_error_text($response, $code));
            return false;
        }
        asn_storage_last_error('');
        return asn_supabase_url() . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . $object;
    }

    $dest = dirname(__DIR__, 2) . '/public/' . $relativePath;
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return false;
    }
    if (!is_writable($dir)) {
        return false;
    }
    if (!@move_uploaded_file($tmpPath, $dest)) {
        if (!@copy($tmpPath, $dest)) {
            return false;
        }
    }
    return $relativePath;
}

function asn_delete_stored($path)
{
    $path = (string)$path;
    if ($path === '') {
        return;
    }
    $prefix = asn_supabase_url() . '/storage/v1/object/public/';
    if ($prefix !== '/storage/v1/object/public/' && strpos($path, $prefix) === 0 && asn_supabase_configured()) {
        $rest = substr($path, strlen($prefix));
        asn_supabase_request('DELETE', '/storage/v1/object/' . $rest, '', null);
        return;
    }
    if (preg_match('#^https?://#i', $path)) {
        return;
    }
    $full = dirname(__DIR__, 2) . '/public/' . ltrim(str_replace('\\', '/', $path), '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

function asn_read_stored_bytes($path)
{
    $path = (string)$path;
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        $ch = curl_init($path);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        asn_curl_apply_tls($ch);
        $data = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($errno !== 0 || $data === false || $code >= 400) {
            return null;
        }
        $mime = $type !== '' ? trim(explode(';', $type)[0]) : 'image/jpeg';
        return ['data' => $data, 'mime' => $mime];
    }

    $full = dirname(__DIR__, 2) . '/public/' . ltrim(str_replace('\\', '/', $path), '/');
    if (!is_file($full)) {
        return null;
    }
    $data = @file_get_contents($full);
    if ($data === false) {
        return null;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        $mime = 'image/png';
    } elseif ($ext === 'svg') {
        $mime = 'image/svg+xml';
    } elseif ($ext === 'gif') {
        $mime = 'image/gif';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    } else {
        $mime = 'image/jpeg';
    }
    return ['data' => $data, 'mime' => $mime];
}

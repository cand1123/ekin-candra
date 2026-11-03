<?php
/**
 * Menyimpan unggahan ke disk lokal bila folder bisa ditulis,
 * atau ke Supabase Storage saat berjalan di Vercel.
 */

function asn_supabase_key()
{
    $key = getenv('SUPABASE_SECRET_KEY');
    if ($key === false || $key === '') {
        $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
    }
    return $key ?: '';
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

function asn_supabase_request($method, $path, $body, $contentType, $extraHeaders = [])
{
    $url = asn_supabase_url() . $path;
    $key = asn_supabase_key();
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
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $response === false ? '' : $response];
}

function asn_supabase_ensure_bucket()
{
    static $ready = false;
    if ($ready) {
        return true;
    }
    $bucket = asn_storage_bucket();
    [$code] = asn_supabase_request(
        'POST',
        '/storage/v1/bucket',
        json_encode([
            'id' => $bucket,
            'name' => $bucket,
            'public' => true,
        ]),
        'application/json'
    );
    $ready = ($code >= 200 && $code < 300) || $code === 409;
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
        asn_supabase_ensure_bucket();
        $bucket = asn_storage_bucket();
        $object = asn_encode_object_path($relativePath);
        $body = file_get_contents($tmpPath);
        if ($body === false) {
            return false;
        }
        [$code] = asn_supabase_request(
            'POST',
            '/storage/v1/object/' . rawurlencode($bucket) . '/' . $object,
            $body,
            $mime ?: 'application/octet-stream',
            ['x-upsert: true']
        );
        if ($code === 404 || $code === 400) {
            asn_supabase_ensure_bucket();
            [$code] = asn_supabase_request(
                'POST',
                '/storage/v1/object/' . rawurlencode($bucket) . '/' . $object,
                $body,
                $mime ?: 'application/octet-stream',
                ['x-upsert: true']
            );
        }
        if ($code < 200 || $code >= 300) {
            return false;
        }
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
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $data = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data === false || $code >= 400) {
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

<?php
/**
 * Menyimpan unggahan ke Supabase Storage (bucket 'bukti') saat dikonfigurasi,
 * atau ke disk lokal bila remote storage belum dikonfigurasi.
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
        if (!is_string($key) || trim($key) === '') {
            $key = $_ENV[$name] ?? ($_SERVER[$name] ?? '');
        }
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
    $url = getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ($_SERVER['SUPABASE_URL'] ?? ''));
    return rtrim((string)$url, '/');
}

/**
 * 1. Pastikan selalu memanggil bucket 'bukti'
 */
function asn_storage_bucket()
{
    $bucket = getenv('SUPABASE_STORAGE_BUCKET');
    if (!$bucket && isset($_ENV['SUPABASE_STORAGE_BUCKET'])) {
        $bucket = $_ENV['SUPABASE_STORAGE_BUCKET'];
    }
    if (!$bucket && isset($_SERVER['SUPABASE_STORAGE_BUCKET'])) {
        $bucket = $_SERVER['SUPABASE_STORAGE_BUCKET'];
    }
    $bucket = trim((string)$bucket);
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
    $forceLocal = getenv('FORCE_LOCAL_STORAGE') ?: ($_ENV['FORCE_LOCAL_STORAGE'] ?? ($_SERVER['FORCE_LOCAL_STORAGE'] ?? ''));
    if ($forceLocal === '1' || strtolower((string)$forceLocal) === 'true') {
        return false;
    }
    return true;
}

function asn_curl_apply_tls($ch)
{
    if (asn_storage_is_local()) {
        // Mode lokal (Laragon/XAMPP): SSL tidak diverifikasi karena tidak ada CA bundle
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        return;
    }

    // Mode produksi: gunakan CA bundle sistem jika tersedia, fallback ke bundled CA
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Vercel PHP tidak selalu memiliki CA bundle default.
    // Coba deteksi CA bundle dari sistem atau path umum.
    $possibleCaBundles = [
        '/etc/ssl/certs/ca-certificates.crt',  // Ubuntu/Debian
        '/etc/pki/tls/certs/ca-bundle.crt',    // CentOS/RHEL
        '/etc/ssl/ca-bundle.pem',              // OpenSUSE
        ini_get('curl.cainfo'),                // Dari php.ini
        ini_get('openssl.cafile'),             // Dari php.ini
    ];
    foreach ($possibleCaBundles as $bundle) {
        if ($bundle && is_file($bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
            break;
        }
    }
}

function asn_supabase_request($method, $path, $body, $contentType, $extraHeaders = [])
{
    $url = asn_supabase_url() . $path;
    $key = asn_supabase_secret_key();
    if ($key === '') {
        asn_storage_last_error('SUPABASE_SECRET_KEY tidak valid atau belum diisi. Pastikan menggunakan secret key (sb_secret_...), bukan publishable key.');
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
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        // Timeout diperketat: Vercel function limit ~25s, jangan habiskan semua untuk 1 request
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        // Paksa IPv4 — Vercel kadang mencoba IPv6 ke Supabase yang tidak tersedia
        // sehingga menyebabkan koneksi diam-diam timeout bukan langsung gagal
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    asn_curl_apply_tls($ch);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Fallback: jika SSL gagal (60/35/77), coba ulang tanpa verifikasi peer
    if (($errno === 60 || $errno === 35 || $errno === 77) && $response === false) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    curl_close($ch);

    if ($errno !== 0 || $response === false) {
        $detail = $error !== '' ? $error : 'koneksi terputus sebelum ada respons dari server';
        asn_storage_last_error('Koneksi ke Supabase Storage gagal (HTTP 0 / cURL ' . $errno . '): ' . $detail);
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
        return 'Koneksi ke Supabase Storage gagal (HTTP 0). Periksa jaringan atau sertifikat SSL Laragon.';
    }
    if ($message === '') {
        return 'Supabase Storage menolak unggahan (HTTP ' . (int)$code . ').';
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

function asn_supabase_ensure_bucket($targetBucket = null)
{
    static $readyBuckets = [];
    $bucket = $targetBucket ? trim((string)$targetBucket) : asn_storage_bucket_id();
    if ($bucket === '') {
        $bucket = 'bukti';
    }
    if (isset($readyBuckets[$bucket])) {
        return true;
    }
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
        $readyBuckets[$bucket] = true;
        return true;
    }
    $readyBuckets[$bucket] = asn_storage_ensure_via_sql();
    return $readyBuckets[$bucket];
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

/**
 * 5. Struktur fungsi acuan Supabase Storage Upload
 * Mengacu pada panggilan:
 * const { data, error } = await supabase.storage
 *   .from('bukti')
 *   .upload(`uploads/${fileName}`, fileObject, {
 *     contentType: fileObject.type || 'image/jpeg',
 *     upsert: true
 *   });
 *
 * @param string $bucket Nama bucket, default 'bukti'
 * @param string $path Path penampungan, misal 'uploads/' . $fileName
 * @param string $fileBody Konten berkas biner
 * @param array $options Opsi unggahan ['contentType' => '...', 'upsert' => true]
 * @return array{data: array{path: string, publicUrl: string}|null, error: string|null}
 */
function asn_supabase_storage_upload($bucket, $path, $fileBody, array $options = [])
{
    $bucketName = !empty($bucket) ? trim((string)$bucket) : 'bukti';
    $contentType = !empty($options['contentType']) ? trim((string)$options['contentType']) : 'image/jpeg';
    $upsert = isset($options['upsert']) ? (bool)$options['upsert'] : true;

    asn_supabase_ensure_bucket($bucketName);

    $encodedBucket = rawurlencode($bucketName);
    $encodedPath = asn_encode_object_path($path);

    $headers = [];
    if ($upsert) {
        $headers[] = 'x-upsert: true';
    }

    [$code, $response] = asn_supabase_request(
        'POST',
        '/storage/v1/object/' . $encodedBucket . '/' . $encodedPath,
        $fileBody,
        $contentType,
        $headers
    );

    if ($code === 404 || $code === 400) {
        asn_supabase_ensure_bucket($bucketName);
        [$code, $response] = asn_supabase_request(
            'POST',
            '/storage/v1/object/' . $encodedBucket . '/' . $encodedPath,
            $fileBody,
            $contentType,
            $headers
        );
    }

    if ($code >= 200 && $code < 300) {
        $publicUrl = asn_supabase_url() . '/storage/v1/object/public/' . $encodedBucket . '/' . $encodedPath;
        return [
            'data' => [
                'path' => $path,
                'publicUrl' => $publicUrl,
            ],
            'error' => null,
        ];
    }

    $errMsg = asn_storage_error_text($response, $code);
    return [
        'data' => null,
        'error' => $errMsg,
    ];
}

/**
 * Menyimpan unggahan berkas ke Supabase Storage (bucket 'bukti') atau fallback lokal.
 *
 * Persyaratan:
 * 1. Memanggil bucket 'bukti'.
 * 2. Opsi upsert: true untuk mencegah error penolakan unggahan / HTTP 0.
 * 3. contentType eksplisit sesuai tipe file (default ke 'image/jpeg').
 * 4. Error handling informatif.
 */
function asn_store_upload($tmpPath, $relativePath, $mime = null)
{
    $relativePath = str_replace('\\', '/', ltrim((string)$relativePath, '/'));
    if ($relativePath === '' || !is_file($tmpPath)) {
        asn_storage_last_error('Berkas sementara tidak ditemukan di server.');
        return false;
    }

    // 3. Sertakan contentType secara eksplisit (default ke 'image/jpeg' jika tidak terdeteksi)
    $contentType = !empty($mime) ? trim((string)$mime) : 'image/jpeg';

    if (asn_use_remote_storage()) {
        asn_storage_last_error('');

        // 1. Pastikan fungsi upload memanggil bucket 'bukti'
        $bucket = asn_storage_bucket_id();
        if ($bucket === '') {
            $bucket = 'bukti';
        }

        $body = file_get_contents($tmpPath);
        if ($body === false) {
            asn_storage_last_error('Gagal membaca isi berkas sementara.');
            return false;
        }

        // 2 & 5. Panggil helper unggahan dengan bucket 'bukti', contentType, dan upsert: true
        $result = asn_supabase_storage_upload($bucket, $relativePath, $body, [
            'contentType' => $contentType,
            'upsert' => true,
        ]);

        if ($result['error'] !== null || empty($result['data']['publicUrl'])) {
            asn_storage_last_error($result['error'] ?? 'Terjadi kesalahan saat mengunggah berkas ke Supabase Storage.');
            return false;
        }

        asn_storage_last_error('');
        return $result['data']['publicUrl'];
    }

    // Fallback lokal (hanya jika remote storage belum dikonfigurasi)
    $dest = dirname(__DIR__, 2) . '/public/' . $relativePath;
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        asn_storage_last_error('Folder penyimpanan berkas lokal tidak dapat dibuat: ' . $dir);
        return false;
    }
    if (!is_writable($dir)) {
        asn_storage_last_error('Folder penyimpanan berkas lokal tidak dapat ditulis: ' . $dir);
        return false;
    }
    if (!@move_uploaded_file($tmpPath, $dest)) {
        if (!@copy($tmpPath, $dest)) {
            asn_storage_last_error('Gagal menyalin berkas yang diunggah ke tujuan lokal: ' . $dest);
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

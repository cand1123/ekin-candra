<?php
/**
 * Google ID Token Verifier — NO DEPENDENCY
 * Verifikasi JWT Google Identity Services (format RS256) menggunakan publik key Google JWKS.
 *
 * Alur:
 *   1. explode JWT → header.payload.signature
 *   2. decode payload base64url (cegah error padding)
 *   3. decode header → ambil kid
 *   4. ambil JWKS Google, cache 24 jam (file di tmp)
 *   5. cari key dengan kid cocok → bangun PEM RSA public key
 *   6. openssl_verify signature dengan sha256WithRSAEncryption
 *   7. cek aud == client_id, iss accounts.google.com / google.com, exp > now, iat
 *
 *   Return: array payload (asosiatif) jika valid, FALSE jika tidak sah.
 */

function google_verify_id_token($idToken, $expectedClientId) {
    if (!is_string($idToken) || $idToken === '') return false;

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) return false;

    list($headerB64, $payloadB64, $signatureB64) = $parts;

    $header = _gjson(_gb64udec($headerB64));
    if (!$header || !is_array($header)) return false;

    if (empty($header['alg']) || $header['alg'] !== 'RS256') return false;
    if (empty($header['kid'])) return false;

    $payloadRaw = _gb64udec($payloadB64);
    $payload = _gjson($payloadRaw);
    if (!$payload || !is_array($payload)) return false;

    $now = time();
    if (isset($payload['exp']) && (int)$payload['exp'] <= $now) return false;
    if (isset($payload['nbf']) && (int)$payload['nbf'] > ($now + 60)) return false;
    if (isset($payload['iat']) && (int)$payload['iat'] > ($now + 60)) return false;

    if (empty($payload['aud'])) return false;
    $aud = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
    if (!in_array($expectedClientId, $aud, true)) return false;

    if (empty($payload['iss'])) return false;
    $iss = $payload['iss'];
    if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') return false;

    if (empty($payload['sub'])) return false;

    $signature = _gb64udec($signatureB64);
    if ($signature === false || $signature === '') return false;

    $jwk = _g_get_jwk($header['kid']);
    if (!$jwk || empty($jwk['n']) || empty($jwk['e'])) return false;

    $pem = _g_jwk_to_pem($jwk['n'], $jwk['e']);
    if (!$pem) return false;

    $publicKey = openssl_pkey_get_public($pem);
    if (!$publicKey) return false;

    $signedContent = $headerB64 . '.' . $payloadB64;
    $ok = openssl_verify($signedContent, $signature, $publicKey, 'sha256WithRSAEncryption');

    return ($ok === 1) ? $payload : false;
}

/* ---------------- INTERNAL HELPERS ---------------- */

function _gb64udec($data) {
    $s = strtr($data, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    return base64_decode($s, true);
}

function _gjson($s) {
    if (!is_string($s) || $s === '') return null;
    $v = json_decode($s, true);
    return (is_array($v) || is_object($v)) ? (array)$v : null;
}

function _g_cache_file() {
    $dir = sys_get_temp_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    }
    return rtrim($dir, '/\\') . '/asn_google_jwks.json';
}

function _g_get_jwk($kid) {
    $cache = _g_cache_file();
    $data = null;
    $refresh = true;

    if (is_file($cache)) {
        $mtime = @filemtime($cache);
        $age = $mtime ? (time() - $mtime) : PHP_INT_MAX;
        if ($age < 86400) {
            $raw = @file_get_contents($cache);
            if ($raw) {
                $data = _gjson($raw);
                if (is_array($data) && !empty($data['keys'])) $refresh = false;
            }
        }
    }

    if ($refresh) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: ASN-Google-OAuth/1.0\r\nAccept: application/json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents('https://www.googleapis.com/oauth2/v3/certs', false, $ctx);
        if (!$raw) {
            if (!$data || empty($data['keys'])) return null;
        } else {
            $newData = _gjson($raw);
            if (is_array($newData) && !empty($newData['keys'])) {
                $data = $newData;
                @file_put_contents($cache, $raw);
            }
        }
    }

    if (empty($data['keys']) || !is_array($data['keys'])) return null;
    foreach ($data['keys'] as $k) {
        $k = (array)$k;
        if (isset($k['kid']) && $k['kid'] === $kid) {
            if (!isset($k['kty']) || $k['kty'] !== 'RSA') continue;
            return $k;
        }
    }
    /* cache stale: coba refresh 1x lagi */
    if ($refresh === false) {
        @unlink($cache);
        return _g_get_jwk($kid);
    }
    return null;
}

function _g_jwk_to_pem($nB64, $eB64) {
    $n = _gb64udec($nB64);
    $e = _gb64udec($eB64);
    if ($n === false || $e === false || $n === '' || $e === '') return false;

    $modSeq = _g_der_int($n);
    $expSeq = _g_der_int($e);
    $rsaPublicKey = _g_der_seq($modSeq . $expSeq); /* PKCS#1 RSAPublicKey ::= SEQUENCE { modulus INTEGER, publicExponent INTEGER } */

    $algId = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; /* rsaEncryption OID + NULL params */
    $spki = _g_der_seq($algId . _g_der_bitstring("\x00" . $rsaPublicKey));

    $pem  = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode($spki), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----\n";
    return $pem;
}

function _g_der_seq($content)  { return "\x30" . _g_der_length(strlen($content)) . $content; }
function _g_der_int($content)  {
    $first = ord($content[0] ?? "\x00");
    if ($first & 0x80) $content = "\x00" . $content;
    while (strlen($content) > 1 && $content[0] === "\x00" && (ord($content[1]) & 0x80) === 0) {
        $content = substr($content, 1);
    }
    return "\x02" . _g_der_length(strlen($content)) . $content;
}
function _g_der_bitstring($content) { return "\x03" . _g_der_length(strlen($content)) . $content; }

function _g_der_length($len) {
    if ($len < 0x80) return chr($len);
    $octets = ltrim(pack('N', $len), "\x00");
    if ($octets === '') $octets = "\x00";
    return chr(0x80 | strlen($octets)) . $octets;
}

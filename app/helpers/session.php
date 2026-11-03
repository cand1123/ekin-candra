<?php
if (ob_get_level() === 0) {
    ob_start();
}

$asnHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$asnOnVercel = getenv('VERCEL') === '1' || (isset($_SERVER['VERCEL']) && (string)$_SERVER['VERCEL'] === '1');
$asnLocal = !$asnOnVercel && (
    $asnHost === ''
    || $asnHost === 'localhost'
    || strpos($asnHost, 'localhost:') === 0
    || strpos($asnHost, '127.0.0.1') === 0
    || strpos($asnHost, '192.168.') === 0
    || strpos($asnHost, '10.') === 0
    || substr($asnHost, -5) === '.test'
    || substr($asnHost, -10) === '.localhost'
);
if (!$asnLocal) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
unset($asnHost, $asnOnVercel, $asnLocal);

require_once dirname(__DIR__) . '/Config/database.php';

class AsnPgSession implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private $db;
    private static $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    private function link()
    {
        if (!$this->db) {
            $this->db = asn_pg_connect();
        }
        if (!$this->db) {
            self::$lastError = 'Koneksi database untuk sesi tidak tersedia.';
            return false;
        }
        $status = pg_transaction_status($this->db);
        if ($status === PGSQL_TRANSACTION_INERROR) {
            @pg_query($this->db, 'ROLLBACK');
        }
        return $this->db;
    }

    private function ttl(): int
    {
        $ttl = (int)ini_get('session.gc_maxlifetime');
        return $ttl > 0 ? $ttl : 43200;
    }

    private function validId(string $id): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9,-]{16,128}$/', $id);
    }

    public function create_sid(): string
    {
        return session_create_id();
    }

    public function open(string $path, string $name): bool
    {
        return $this->link() !== false;
    }

    public function close(): bool
    {
        return true;
    }

    public function validateId(string $id): bool
    {
        $db = $this->link();
        if (!$db || !$this->validId($id)) {
            return false;
        }
        $res = @pg_query_params(
            $db,
            'SELECT 1 FROM php_sessions WHERE id = $1 AND expires_at > NOW()',
            [$id]
        );
        return $res !== false && pg_num_rows($res) > 0;
    }

    public function read(string $id): string|false
    {
        $db = $this->link();
        if (!$db || !$this->validId($id)) {
            return '';
        }
        $res = @pg_query_params(
            $db,
            'SELECT data FROM php_sessions WHERE id = $1 AND expires_at > NOW()',
            [$id]
        );
        if ($res && ($row = pg_fetch_assoc($res)) && isset($row['data'])) {
            $raw = base64_decode((string)$row['data'], true);
            return $raw === false ? '' : $raw;
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        $db = $this->link();
        if (!$db || !$this->validId($id)) {
            self::$lastError = 'ID sesi tidak valid.';
            return false;
        }
        $res = @pg_query_params(
            $db,
            'INSERT INTO php_sessions (id, data, expires_at)
             VALUES ($1, $2, NOW() + ($3::int * INTERVAL \'1 second\'))
             ON CONFLICT (id) DO UPDATE
             SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at',
            [$id, base64_encode($data), (string)$this->ttl()]
        );
        if ($res === false) {
            self::$lastError = trim(pg_last_error($db));
            return false;
        }
        self::$lastError = '';
        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $db = $this->link();
        if (!$db || !$this->validId($id)) {
            return false;
        }
        $res = @pg_query_params(
            $db,
            'UPDATE php_sessions
             SET expires_at = NOW() + ($2::int * INTERVAL \'1 second\')
             WHERE id = $1',
            [$id, (string)$this->ttl()]
        );
        if ($res === false) {
            self::$lastError = trim(pg_last_error($db));
            return $this->write($id, $data);
        }
        if (pg_affected_rows($res) < 1) {
            return $this->write($id, $data);
        }
        return true;
    }

    public function destroy(string $id): bool
    {
        $db = $this->link();
        if (!$db) {
            return false;
        }
        $res = @pg_query_params($db, 'DELETE FROM php_sessions WHERE id = $1', [$id]);
        return $res !== false;
    }

    public function gc(int $max_lifetime): int|false
    {
        $db = $this->link();
        if (!$db) {
            return false;
        }
        $res = @pg_query($db, 'DELETE FROM php_sessions WHERE expires_at < NOW()');
        if ($res === false) {
            return false;
        }
        return pg_affected_rows($res);
    }
}

function asn_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] ?? ''));
    if ($proto === 'https') {
        return true;
    }
    if (strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https') {
        return true;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $onVercel = getenv('VERCEL') === '1' || (isset($_SERVER['VERCEL']) && (string)$_SERVER['VERCEL'] === '1');
    $local = $host === '' || $host === 'localhost' || strpos($host, 'localhost:') === 0 || strpos($host, '127.0.0.1') === 0;
    return $onVercel && !$local;
}

if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    $lifetime = 43200;
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.lazy_write', '0');
    ini_set('session.cache_limiter', 'nocache');
    session_name('ASNSESSID');
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => asn_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (asn_pg_connect()) {
        $handler = new AsnPgSession();
        $GLOBALS['asn_pg_session_handler'] = $handler;
        session_set_save_handler($handler, true);
        register_shutdown_function('session_write_close');
    } else {
        $sessPath = session_save_path();
        if ($sessPath === '' || stripos($sessPath, 'laragon') !== false || !is_dir($sessPath) || !is_writable($sessPath)) {
            $tmpDir = sys_get_temp_dir();
            if (is_dir($tmpDir) && is_writable($tmpDir)) {
                session_save_path($tmpDir);
            }
        }
    }
    session_start();
}

function is_logged_in()
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function asn_begin_user_session($userId, $username): bool
{
    $userId = (int)$userId;
    if ($userId <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = (string)$username;
    session_regenerate_id(true);
    return session_write_close();
}

function asn_end_user_session(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => ($params['path'] ?? '') !== '' ? $params['path'] : '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => ($params['samesite'] ?? '') !== '' ? $params['samesite'] : 'Lax',
    ]);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

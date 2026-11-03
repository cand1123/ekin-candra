<?php
require_once __DIR__ . '/../helpers/session.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/app/Config/database.php';

define('GOOGLE_CLIENT_ID', '101348972853-nnnrbi4a2pk8gavkjtuoifn9m5cq43o9.apps.googleusercontent.com');

/* ============================================================
 * AUTO-MIGRATE DEFENSIVE: Pastikan kolom Google OAuth tersedia
 *   (menghindari Fatal Error "bind_param() on bool" ketika
 *    user belum menjalankan migrate_google_login.php)
 * ============================================================ */
function asn_login_ensure_columns($mysqli) {
    static $done = false;
    if ($done) return true;
    $queries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL AFTER nama",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL AFTER email",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_provider ENUM('local','google') NOT NULL DEFAULT 'local' AFTER google_id",
    ];
    $idx = [
        "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_google_id (google_id)",
        "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_email (email)",
    ];
    foreach ($queries as $q) { @$mysqli->query($q); }
    /* Kolom password: biarkan nullable untuk user Google (jika belum di-migrate) */
    @$mysqli->query("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) DEFAULT NULL");
    foreach ($idx as $q) { @$mysqli->query($q); }
    $done = true;
    return true;
}
asn_login_ensure_columns($mysqli);

$mode = $_GET['mode'] ?? 'login';
if (!in_array($mode, ['login', 'daftar'], true)) $mode = 'login';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? 'login';

    if ($aksi === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $stmt = $mysqli->prepare('SELECT id, username, password, auth_provider FROM users WHERE username = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Terjadi kesalahan sistem. Silakan refresh halaman lalu coba lagi.';
            } else {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                $provider = $user['auth_provider'] ?? 'local';
                if ($user && $provider !== 'google' && !empty($user['password']) && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Username atau password salah. Atau akun ini login menggunakan Google.';
                }
            }
        }
    } elseif ($aksi === 'google_login') {
        $credential = trim($_POST['credential'] ?? '');
        if (empty($credential)) {
            $error = 'Token Google tidak valid. Silakan coba lagi.';
        } else {
            require_once dirname(__DIR__, 2) . '/public/google_verify.php';
            $payload = google_verify_id_token($credential, GOOGLE_CLIENT_ID);
            if (!$payload) {
                $error = 'Verifikasi Google gagal. Token tidak sah atau sudah kedaluwarsa.';
            } else {
                $googleId  = $payload['sub'] ?? '';
                $email     = isset($payload['email']) ? strtolower(trim($payload['email'])) : '';
                $nama      = trim($payload['name'] ?? ($payload['given_name'] ?? $email));
                $fotoGoogle = trim($payload['picture'] ?? '');

                if (empty($googleId) || empty($email)) {
                    $error = 'Data Google tidak lengkap (sub/email). Silakan coba lagi.';
                } else {
                    $userId = null;
                    $stmt = $mysqli->prepare('SELECT id, username, google_id, email, auth_provider, foto FROM users WHERE google_id = ? OR email = ? LIMIT 1');
                    if (!$stmt) {
                        $error = 'Terjadi kesalahan sistem. Silakan refresh lalu coba lagi.';
                    } else {
                        $stmt->bind_param('ss', $googleId, $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $existing = $result->fetch_assoc();
                        $stmt->close();

                        if ($existing) {
                            $userId = (int)$existing['id'];
                            $upd = $mysqli->prepare("UPDATE users SET google_id = ?, auth_provider = 'google', email = ?, foto = COALESCE(NULLIF(foto,''), ?) WHERE id = ? LIMIT 1");
                            if ($upd) {
                                $upd->bind_param('sssi', $googleId, $email, $fotoGoogle, $userId);
                                $upd->execute();
                                $upd->close();
                            }
                            if (empty($existing['username'])) {
                                $usernameDefault = preg_replace('/[^a-z0-9_]/', '', str_replace(['@','.'], '_', strtolower($email)));
                                $usernameDefault = substr($usernameDefault, 0, 32);
                                $upd2 = $mysqli->prepare('UPDATE users SET username = ? WHERE id = ? AND (username IS NULL OR username = "") LIMIT 1');
                                if ($upd2) {
                                    $upd2->bind_param('si', $usernameDefault, $userId);
                                    $upd2->execute();
                                    $upd2->close();
                                }
                            }
                        } else {
                            $usernameDefault = preg_replace('/[^a-z0-9_]/', '', str_replace(['@','.'], '_', strtolower($email)));
                            $usernameDefault = substr($usernameDefault, 0, 32);
                            $base = $usernameDefault;
                            $i = 1;
                            do {
                                $cek = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                                $ada = false;
                                if ($cek) {
                                    $cek->bind_param('s', $usernameDefault);
                                    $cek->execute();
                                    $cek->store_result();
                                    $ada = $cek->num_rows > 0;
                                    $cek->close();
                                }
                                if ($ada) {
                                    $i++;
                                    $usernameDefault = $base . $i;
                                }
                            } while ($ada);

                            $ins = $mysqli->prepare("INSERT INTO users (nama, nip, jabatan, unit_kerja, instansi, username, password, email, google_id, auth_provider, foto) VALUES (?, '', '', '', '', ?, NULL, ?, ?, 'google', ?)");
                            if (!$ins) {
                                $error = 'Gagal menyiapkan pembuatan akun. Silakan coba lagi.';
                                $userId = null;
                            } else {
                                $ins->bind_param('sssss', $nama, $usernameDefault, $email, $googleId, $fotoGoogle);
                                if (!$ins->execute()) {
                                    $error = 'Gagal membuat akun Google. Silakan coba lagi.';
                                    $userId = null;
                                } else {
                                    $userId = $mysqli->insert_id;
                                }
                                $ins->close();
                            }
                        }
                    }

                    if (!empty($userId)) {
                        session_regenerate_id(true);
                        $unameStmt = $mysqli->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                        $unameFinal = '';
                        if ($unameStmt) {
                            $unameStmt->bind_param('i', $userId);
                            $unameStmt->execute();
                            $unameStmt->bind_result($unameFinal);
                            $unameStmt->fetch();
                            $unameStmt->close();
                        }

                        $_SESSION['user_id'] = $userId;
                        $_SESSION['username'] = $unameFinal ?: 'user_' . $userId;
                        header('Location: dashboard.php');
                        exit;
                    } elseif (empty($error)) {
                        $error = 'Login Google gagal. Silakan coba lagi atau masuk dengan username/password.';
                    }
                }
            }
        }
    } elseif ($aksi === 'daftar') {
        $nama     = trim($_POST['nama'] ?? '');
        $nip      = trim($_POST['nip'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $unit     = trim($_POST['unit_kerja'] ?? '');
        $instansi = trim($_POST['instansi'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $pass1    = $_POST['password'] ?? '';
        $pass2    = $_POST['password_confirm'] ?? '';

        if (empty($nama) || empty($username) || empty($pass1)) {
            $error = 'Nama, Username, dan Password harus diisi.';
            $mode = 'daftar';
        } elseif (strlen($pass1) < 6) {
            $error = 'Password minimal 6 karakter.';
            $mode = 'daftar';
        } elseif ($pass1 !== $pass2) {
            $error = 'Konfirmasi password tidak sama.';
            $mode = 'daftar';
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $ada = false;
            if (!$stmt) {
                $error = 'Terjadi kesalahan sistem. Silakan refresh lalu coba lagi.';
                $mode = 'daftar';
            } else {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();
                $ada = $stmt->num_rows > 0;
                $stmt->close();
            }

            if ($ada) {
                $error = 'Username sudah dipakai. Silakan pilih username lain.';
                $mode = 'daftar';
            } else {
                $hash = password_hash($pass1, PASSWORD_DEFAULT);
                $emailCol = $email ?: null;
                $stmt = $mysqli->prepare('INSERT INTO users (nama, nip, jabatan, unit_kerja, instansi, username, password, email, auth_provider) VALUES (?,?,?,?,?,?,?,?,\'local\')');
                $ok = false;
                if ($stmt) {
                    $stmt->bind_param('ssssssss', $nama, $nip, $jabatan, $unit, $instansi, $username, $hash, $emailCol);
                    $ok = $stmt->execute();
                    $stmt->close();
                }

                if ($ok) {
                    $success = 'Pendaftaran berhasil! Silakan masuk dengan akun baru Anda.';
                    $mode = 'login';
                } else {
                    $error = 'Pendaftaran gagal. Silakan coba lagi.';
                    $mode = 'daftar';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'daftar' ? 'Daftar Akun' : 'Masuk' ?> - Sistem Laporan ASN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="auth-logo-icon" style="color: var(--asn-primary); background: var(--asn-soft-blue);">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div class="text-center">
                    <h1>Sistem Laporan ASN</h1>
                    <p class="auth-kemdikbud-sub" style="color: var(--asn-text-muted); font-size: var(--fs-xs); margin-bottom: 0.35rem;">Kementerian Pendidikan, Kebudayaan, Riset dan Teknologi</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success mb-3" role="alert">
                    <i class="bi bi-check-circle me-1"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'login'): ?>
                <div class="mb-3">
                    <div class="auth-title">Selamat Datang Kembali</div>
                    <div class="auth-subtitle">Silakan masuk untuk membuka dashboard & laporan pribadi Anda.</div>
                </div>

                <form method="POST" action="login.php?mode=login" novalidate>
                    <input type="hidden" name="aksi" value="login">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" required autofocus placeholder="Masukkan username Anda">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required placeholder="Masukkan password Anda">
                            <button type="button" class="input-group-text bg-white border-start-0 text-muted" onclick="togglePw(this,'password')" aria-label="Tampilkan password"><i class="bi bi-eye-slash"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        Masuk
                    </button>
                </form>

                <form method="POST" action="login.php?mode=login" id="googleForm" novalidate>
                    <input type="hidden" name="aksi" value="google_login">
                    <input type="hidden" name="credential" id="google_credential" value="">
                </form>

                <div class="auth-divider my-4"><span>atau masuk dengan</span></div>

                <div id="g_id_onload"
                     data-client_id="<?= GOOGLE_CLIENT_ID ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="googleOnSuccess"
                     data-auto_prompt="false"
                     data-itp_support="true">
                </div>
                <div class="gsi-wrapper">
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left">
                    </div>
                </div>

                <div class="auth-switch">
                    Belum punya akun? <a href="login.php?mode=daftar">Daftar Disini</a>
                </div>
            <?php else: ?>
                <div class="mb-3">
                    <div class="auth-title">Buat Akun Pribadi</div>
                    <div class="auth-subtitle">Isi data diri untuk mulai mencatat & merekap laporan kegiatan ASN.</div>
                </div>
                <form method="POST" action="login.php?mode=daftar" novalidate>
                    <input type="hidden" name="aksi" value="daftar">
                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="nama" name="nama" required autofocus placeholder="Contoh: Novian Candra K">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Alamat Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="nama@kemdikbud.go.id">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="nip" class="form-label fw-semibold">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip" placeholder="Opsional">
                        </div>
                        <div class="col-md-6">
                            <label for="jabatan" class="form-label fw-semibold">Jabatan</label>
                            <input type="text" class="form-control" id="jabatan" name="jabatan" placeholder="Contoh: Guru">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="unit_kerja" class="form-label fw-semibold">Unit Kerja</label>
                            <input type="text" class="form-control" id="unit_kerja" name="unit_kerja" placeholder="Opsional">
                        </div>
                        <div class="col-md-6">
                            <label for="instansi" class="form-label fw-semibold">Instansi</label>
                            <input type="text" class="form-control" id="instansi" name="instansi" placeholder="Opsional">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-at"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" required placeholder="Pilih username untuk login">
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" minlength="6" required placeholder="Min. 6 karakter">
                                <button type="button" class="input-group-text bg-white border-start-0 text-muted" onclick="togglePw(this,'password')" aria-label="Tampilkan password"><i class="bi bi-eye-slash"></i></button>
                            </div>
                            <div class="form-text"><i class="bi bi-info-circle me-1"></i>Minimal 6 karakter.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password_confirm" name="password_confirm" minlength="6" required placeholder="Ulangi password">
                                <button type="button" class="input-group-text bg-white border-start-0 text-muted" onclick="togglePw(this,'password_confirm')" aria-label="Tampilkan password"><i class="bi bi-eye-slash"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        Daftar Akun
                    </button>
                </form>
                <div class="auth-switch">
                    Sudah punya akun? <a href="login.php?mode=login">Masuk Disini</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePw(btn, id) {
            var inp = document.getElementById(id);
            if (!inp) return;
            if (inp.type === 'password') {
                inp.type = 'text';
                btn.innerHTML = '<i class="bi bi-eye"></i>';
            } else {
                inp.type = 'password';
                btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            }
        }
        function googleOnSuccess(response) {
            if (!response || !response.credential) return;
            var tokenInput = document.getElementById('google_credential');
            if (!tokenInput) return;
            tokenInput.value = response.credential;
            document.getElementById('googleForm').submit();
        }
    </script>
    <?php if ($mode === 'login'): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</body>
</html>

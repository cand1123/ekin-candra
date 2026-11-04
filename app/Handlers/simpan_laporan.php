<?php
require_once dirname(__DIR__) . '/helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: laporan_harian.php');
    exit;
}

$uid = current_user_id();
$aksi = $_POST['aksi'] ?? 'tambah';
$laporan_id = (int)($_POST['laporan_id'] ?? 0);

$tanggal = $_POST['tanggal'] ?? '';
$rhk_id = (int)($_POST['rhk_id'] ?? 0);
$uraian = trim($_POST['uraian_kegiatan'] ?? '');
$jam_mulai = $_POST['jam_mulai'] ?? '00:00';
$jam_selesai = $_POST['jam_selesai'] ?? '00:00';
$jumlah = max(1, (int)($_POST['jumlah_kegiatan'] ?? 1));
$hasil = trim($_POST['hasil'] ?? '');

if (empty($tanggal) || $rhk_id <= 0 || empty($uraian)) {
    if ($aksi === 'edit' && $laporan_id > 0) {
        header('Location: edit_laporan.php?id=' . $laporan_id . '&error=1');
    } else {
        header('Location: laporan_harian.php?tambah=1&error=1');
    }
    exit;
}

function build_redirect($aksi, $laporan_id, $statusParams) {
    $qs = $statusParams;
    return 'laporan_harian.php?' . http_build_query($qs);
}

function detect_image_type($filePath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $byExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'jpe' => 'image/jpeg', 'png' => 'image/png'];
    $mimeExt = isset($byExt[$ext]) ? $byExt[$ext] : null;

    $fh = @fopen($filePath, 'rb');
    if ($fh) {
        $head = fread($fh, 12);
        fclose($fh);
        if ($head !== false && strlen($head) >= 8) {
            if (substr($head, 0, 3) === "\xFF\xD8\xFF") {
                $finfo = 'image/jpeg';
            } elseif (substr($head, 0, 8) === "\x89PNG\r\n\x1a\n") {
                $finfo = 'image/png';
            } else {
                $finfo = false;
            }
        } else {
            $finfo = false;
        }
    } else {
        $finfo = false;
    }

    if ($finfo && $mimeExt && $finfo !== $mimeExt) {
        if ($finfo === 'image/jpeg' && $mimeExt === 'image/jpeg') {
        } else {
            return $finfo;
        }
    }

    if ($finfo) return $finfo;
    return $mimeExt;
}

function parse_upload_errors($errors) {
    $codes = [
        UPLOAD_ERR_INI_SIZE => 'Berkas melebihi batas upload_max_filesize pada php.ini',
        UPLOAD_ERR_FORM_SIZE => 'Berkas melebihi batas MAX_FILE_SIZE formulir',
        UPLOAD_ERR_PARTIAL => 'Berkas hanya terunggah sebagian',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara unggah tidak tersedia',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis berkas ke disk',
        UPLOAD_ERR_EXTENSION => 'Unggah dihentikan oleh ekstensi PHP'
    ];
    $msgs = [];
    foreach ($errors as $idx => $code) {
        if ($code === UPLOAD_ERR_OK || $code === UPLOAD_ERR_NO_FILE) continue;
        $msgs[] = 'Berkas ke-' . ($idx + 1) . ': ' . ($codes[$code] ?? 'Kode kesalahan ' . $code);
    }
    return $msgs;
}

function get_table_columns($mysqli, $table) {
    $cols = [];
    $safeTable = str_replace('`', '', $table);
    $res = $mysqli->query('DESCRIBE `' . $safeTable . '`');
    if (!$res) return $cols;
    while ($r = $res->fetch_assoc()) $cols[$r['Field']] = true;
    return $cols;
}

$BK_COLS = get_table_columns($mysqli, 'bukti_kegiatan');
$BK_HAS_USER_ID = isset($BK_COLS['user_id']);
$BK_HAS_META = isset($BK_COLS['mime_type']) && isset($BK_COLS['ukuran_file']);

$mysqli->begin_transaction();
$uploadErrors = [];
$uploadedCount = 0;
try {
    if ($aksi === 'edit' && $laporan_id > 0) {
        $stmt = $mysqli->prepare('UPDATE laporan_harian SET rhk_id=?, tanggal=?, uraian_kegiatan=?, jam_mulai=?, jam_selesai=?, jumlah_kegiatan=?, hasil=? WHERE id=? AND user_id=?');
        $stmt->bind_param('issssiisi', $rhk_id, $tanggal, $uraian, $jam_mulai, $jam_selesai, $jumlah, $hasil, $laporan_id, $uid);
        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui data: ' . $stmt->error);
        }
        $stmt->close();
        $newId = $laporan_id;
    } else {
        $stmt = $mysqli->prepare('INSERT INTO laporan_harian (user_id, rhk_id, tanggal, uraian_kegiatan, jam_mulai, jam_selesai, jumlah_kegiatan, hasil) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->bind_param('iissssis', $uid, $rhk_id, $tanggal, $uraian, $jam_mulai, $jam_selesai, $jumlah, $hasil);
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan data: ' . $stmt->error);
        }
        $newId = $mysqli->insert_id;
        $stmt->close();
    }

    $allowedExt = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png'];
    $bukti = $_FILES['bukti'] ?? null;
    $hasFiles = false;
    $cleanNames = [];
    $cleanErrors = [];
    $cleanTmp = [];
    $cleanSizes = [];
    if ($bukti && isset($bukti['name']) && is_array($bukti['name'])) {
        $n = count($bukti['name']);
        for ($i = 0; $i < $n; $i++) {
            if (!isset($bukti['error'][$i]) || $bukti['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $nm = isset($bukti['name'][$i]) ? trim($bukti['name'][$i]) : '';
            if ($nm === '') continue;
            $cleanNames[] = $nm;
            $cleanErrors[] = (int)$bukti['error'][$i];
            $cleanTmp[] = $bukti['tmp_name'][$i] ?? '';
            $cleanSizes[] = isset($bukti['size'][$i]) ? (int)$bukti['size'][$i] : 0;
            $hasFiles = true;
        }
    }

    if ($hasFiles) {
        $total = count($cleanNames);

        $errMsgs = parse_upload_errors($cleanErrors);
        if (!empty($errMsgs)) {
            throw new Exception('Gagal mengunggah berkas: ' . implode(' | ', $errMsgs));
        }

        $stmtCount = $mysqli->prepare('SELECT COUNT(*) FROM bukti_kegiatan WHERE laporan_id=?');
        $stmtCount->bind_param('i', $newId);
        $stmtCount->execute();
        $stmtCount->bind_result($currCount);
        $stmtCount->fetch();
        $stmtCount->close();
        $allowedTotal = 5 - $currCount;
        if ($allowedTotal <= 0) {
            throw new Exception('Maksimal 5 foto per laporan, tidak dapat menambah bukti lagi.');
        }

        $uploadedPaths = []; // Track paths for cleanup if error occurs
        for ($i = 0; $i < $total; $i++) {
            if ($uploadedCount >= $allowedTotal) {
                break;
            }
            if ($cleanErrors[$i] !== UPLOAD_ERR_OK) continue;

            $tmp = $cleanTmp[$i];
            $orig = $cleanNames[$i];
            $size = $cleanSizes[$i];

            if (!is_uploaded_file($tmp)) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                throw new Exception('Berkas "' . htmlspecialchars($orig) . '" bukan hasil unggah yang sah.');
            }
            if ($size <= 0) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                throw new Exception('Berkas "' . htmlspecialchars($orig) . '" kosong.');
            }
            if ($size > 5 * 1024 * 1024) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                throw new Exception('Ukuran berkas "' . htmlspecialchars($orig) . '" melebihi batas 5 MB (' . round($size / 1048576, 2) . ' MB).');
            }

            $extRaw = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $detected = detect_image_type($tmp, $orig);
            if (!$detected || !in_array($detected, ['image/jpeg', 'image/png'], true)) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                throw new Exception('Format berkas "' . htmlspecialchars($orig) . '" tidak didukung (hanya JPG/PNG).');
            }
            if ($extRaw && !isset($allowedExt[$extRaw])) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                throw new Exception('Ekstensi berkas "' . htmlspecialchars($orig) . '" tidak diizinkan (hanya .jpg/.jpeg/.png).');
            }

            $ext = $detected === 'image/png' ? 'png' : 'jpg';
            $namaBaru = 'bukti_' . $newId . '_' . $uid . '_' . date('YmdHis') . '_' . $i . '_' . uniqid('', true) . '.' . $ext;

            // 1. Memanggil bucket 'bukti'
            // 2. Menggunakan opsi upsert: true
            // 3. Sertakan contentType secara eksplisit (default ke 'image/jpeg')
            $contentType = !empty($detected) ? $detected : 'image/jpeg';
            $pathRel = asn_store_upload($tmp, 'uploads/bukti/' . $namaBaru, $contentType);

            // 4. Tambahkan error handling yang jelas: jika terjadi error dari Supabase Storage, batalkan proses simpan data laporan
            if ($pathRel === false) {
                foreach ($uploadedPaths as $p) {
                    asn_delete_stored($p);
                }
                $detail = asn_storage_last_error();
                $errText = 'Gagal mengunggah berkas bukti "' . htmlspecialchars($orig) . '" ke Supabase Storage. '
                    . ($detail !== '' ? $detail : 'Terjadi kendala pada layanan penyimpanan cloud.')
                    . ' Proses simpan data laporan dibatalkan.';
                throw new Exception($errText);
            }

            $uploadedPaths[] = $pathRel;

            if ($BK_HAS_USER_ID && $BK_HAS_META) {
                $stmtI = $mysqli->prepare('INSERT INTO bukti_kegiatan (laporan_id, user_id, nama_file, path_file, mime_type, ukuran_file) VALUES (?,?,?,?,?,?)');
                if (!$stmtI) {
                    foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                    throw new Exception('Gagal menyiapkan perintah simpan bukti (skema baru): ' . $mysqli->error);
                }
                $stmtI->bind_param('iisssi', $newId, $uid, $orig, $pathRel, $contentType, $size);
            } elseif ($BK_HAS_USER_ID) {
                $stmtI = $mysqli->prepare('INSERT INTO bukti_kegiatan (laporan_id, user_id, nama_file, path_file) VALUES (?,?,?,?)');
                if (!$stmtI) {
                    foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                    throw new Exception('Gagal menyiapkan perintah simpan bukti (skema menengah): ' . $mysqli->error);
                }
                $stmtI->bind_param('iiss', $newId, $uid, $orig, $pathRel);
            } else {
                $stmtI = $mysqli->prepare('INSERT INTO bukti_kegiatan (laporan_id, nama_file, path_file) VALUES (?,?,?)');
                if (!$stmtI) {
                    foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                    throw new Exception('Gagal menyiapkan perintah simpan bukti (skema lama): ' . $mysqli->error);
                }
                $stmtI->bind_param('iss', $newId, $orig, $pathRel);
            }

            if (!$stmtI->execute()) {
                foreach ($uploadedPaths as $p) { asn_delete_stored($p); }
                $stmtI->close();
                throw new Exception('Gagal menyimpan metadata bukti: ' . $mysqli->error);
            }
            $stmtI->close();
            $uploadedCount++;
        }
    }

    $mysqli->commit();
    $params = ['status' => 'sukses'];
    if ($uploadedCount > 0) $params['up_ok'] = $uploadedCount;
    if (!empty($uploadErrors)) {
        $params['up_warn'] = 1;
        $params['up_msg'] = implode(' | ', $uploadErrors);
    }
    header('Location: ' . build_redirect($aksi, $laporan_id, $params));
    exit;

} catch (Exception $e) {
    $mysqli->rollback();
    $params = ['error' => 2, 'msg' => $e->getMessage()];
    if ($uploadedCount > 0) $params['up_ok'] = $uploadedCount;
    if (!empty($uploadErrors)) {
        $params['up_warn'] = 1;
        $params['up_msg'] = implode(' | ', $uploadErrors);
    }
    if ($aksi === 'edit' && $laporan_id > 0) {
        $params['id'] = $laporan_id;
        header('Location: edit_laporan.php?' . http_build_query($params));
    } else {
        $params['tambah'] = 1; /* buka kembali modal Tambah Laporan di halaman daftar */
        header('Location: ' . build_redirect($aksi, $laporan_id, $params));
    }
    exit;
}

<?php
require_once dirname(__DIR__) . '/helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$uid = current_user_id();
$id = (int)($_GET['id'] ?? 0);
$laporan_id = (int)($_GET['laporan_id'] ?? 0);
if ($id <= 0) { header('Location: laporan_harian.php'); exit; }

$stmt = $mysqli->prepare('SELECT bk.id, bk.path_file FROM bukti_kegiatan bk JOIN laporan_harian lh ON bk.laporan_id = lh.id WHERE bk.id = ? AND lh.user_id = ?');
$stmt->bind_param('ii', $id, $uid); $stmt->execute();
$bukti = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($bukti) {
    $path = dirname(__DIR__, 2) . '/public/' . $bukti['path_file'];
    if (file_exists($path)) @unlink($path);
    $stmt = $mysqli->prepare('DELETE FROM bukti_kegiatan WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: edit_laporan.php?id=' . $laporan_id . '&hapus_bukti=sukses');
exit;

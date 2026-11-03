<?php
require_once dirname(__DIR__) . '/helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

$uid = current_user_id();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: laporan_harian.php'); exit; }

$stmt = $mysqli->prepare('SELECT b.path_file FROM bukti_kegiatan b JOIN laporan_harian lh ON b.laporan_id = lh.id WHERE lh.id = ? AND lh.user_id = ?');
$stmt->bind_param('ii', $id, $uid); $stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    asn_delete_stored($r['path_file']);
}
$stmt->close();

$stmt = $mysqli->prepare('DELETE FROM laporan_harian WHERE id=? AND user_id=?');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$stmt->close();

header('Location: laporan_harian.php?status=hapus');
exit;

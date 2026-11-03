<?php
require_once dirname(__DIR__) . '/helpers/session.php';
require_login();
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

header('Content-Type: application/json');
$uid = current_user_id();
$laporan_id = (int)($_GET['laporan_id'] ?? 0);

$stmt = $mysqli->prepare('SELECT bk.id, bk.nama_file, bk.path_file FROM bukti_kegiatan bk JOIN laporan_harian lh ON bk.laporan_id = lh.id WHERE lh.id = ? AND lh.user_id = ?');
$stmt->bind_param('ii', $laporan_id, $uid);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;
$stmt->close();
echo json_encode($out);

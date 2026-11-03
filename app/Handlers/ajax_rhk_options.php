<?php
require_once dirname(__DIR__) . '/helpers/session.php';
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized', 'data' => []]);
    exit;
}
$uid = (int)current_user_id();

function ensure_rhk_schema_ready($mysqli) {
    $rhkCols = [];
    $rhkColsRes = $mysqli->query('DESCRIBE rencana_hasil_kerja');
    if ($rhkColsRes) {
        while ($row = $rhkColsRes->fetch_assoc()) {
            $rhkCols[$row['Field']] = true;
        }
        $rhkColsRes->free();
    }
    if (!isset($rhkCols['bulan'])) {
        @$mysqli->query("ALTER TABLE rencana_hasil_kerja ADD COLUMN bulan TINYINT NOT NULL DEFAULT 0 COMMENT '0=Semua Bulan, 1-12=Januari-Desember' AFTER tahun");
        @$mysqli->query("ALTER TABLE rencana_hasil_kerja ADD INDEX idx_rhk_bulan_tahun (user_id, bulan, tahun)");
    }

    $raTableExists = false;
    $raColsRes = $mysqli->query('DESCRIBE rencana_aksi');
    if ($raColsRes) {
        $raTableExists = true;
        $raColsRes->free();
    }
    if (!$raTableExists) {
        @$mysqli->query("CREATE TABLE rencana_aksi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rhk_id INT NOT NULL,
            bulan TINYINT NOT NULL,
            tahun YEAR NOT NULL,
            indikator TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ra_rhk_bulan_tahun (rhk_id, bulan, tahun),
            INDEX idx_ra_rhk (rhk_id),
            CONSTRAINT fk_ra_rhk FOREIGN KEY (rhk_id)
                REFERENCES rencana_hasil_kerja(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('n');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

$out = [];
if ($mysqli) {
    ensure_rhk_schema_ready($mysqli);

    $sql = 'SELECT DISTINCT r.id, r.nama_rhk, r.indikator, r.bulan, r.tahun,
                   TRIM(ra.indikator) AS label
            FROM rencana_hasil_kerja r
            INNER JOIN rencana_aksi ra ON ra.rhk_id = r.id AND ra.bulan = ? AND ra.tahun = ?
            WHERE r.user_id = ? AND (r.bulan = ? OR r.bulan = 0) AND r.tahun = ?
              AND NULLIF(TRIM(ra.indikator), \'\') IS NOT NULL
            ORDER BY label ASC, r.nama_rhk ASC, r.id DESC';
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('iiiii', $bulan, $tahun, $uid, $bulan, $tahun);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $label = trim((string)($row['label'] ?? ''));
                $nama = trim((string)($row['nama_rhk'] ?? ''));
                $ind = trim((string)($row['indikator'] ?? ''));
                if ($label === '') {
                    $label = '[RHK #' . (int)$row['id'] . ']';
                }
                $out[] = [
                    'id' => (int)$row['id'],
                    'label' => $label,
                    'nama_rhk' => $nama,
                    'indikator' => $ind,
                    'semua_bulan' => ((int)($row['bulan'] ?? 0) === 0),
                    'bulan' => (int)($row['bulan'] ?? 0),
                    'tahun' => (int)($row['tahun'] ?? 0),
                ];
            }
        }
        $stmt->close();
    }
}

echo json_encode(['ok' => true, 'bulan' => $bulan, 'tahun' => $tahun, 'data' => $out], JSON_UNESCAPED_UNICODE);
exit;

<?php
// Generator: Isi template Word di format-laporan/*.docx DENGAN DATA DB LANGSUNG.
// .docx = ZIP berisi XML, jadi TIDAK PERLU library eksternal (tidak install phpword).
// Output: DOWNLOAD .docx (user buka di Microsoft Word / LibreOffice -> Save As PDF -> PDF sesuai format Word PERSIS).
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

// --- Parameter ---
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$laporanId = isset($_GET['id_laporan']) ? (int)$_GET['id_laporan'] : null;
$type = isset($_GET['type']) && $_GET['type'] === 'bulanan' ? 'bulanan' : 'harian';
$download = !isset($_GET['download']) || $_GET['download'] !== '0';

// --- Security: WAJIB LOGIN (kecuali CLI mode/debug, allow user_id via GET jika user == admin login dg SESSION) ---
if (php_sapi_name() !== 'cli') {
    require_once dirname(__DIR__) . '/helpers/session.php';
    require_login();
}
require_once dirname(__DIR__, 2) . '/app/Config/database.php';

/** @var mysqli $mysqli */

if (php_sapi_name() === 'cli' && !isset($_SESSION['user_id'])) {
    // CLI debug: pakai user admin (ID=1) hardcode
    $uid = 1;
} else {
    $uid = current_user_id();
    if (!$uid) { http_response_code(403); echo "Forbidden"; exit; }
}

// --- Ambil user data ---
$stmtU = $mysqli->prepare('SELECT id, nama, nip, jabatan, unit_kerja, instansi FROM users WHERE id=? LIMIT 1');
$stmtU->bind_param('i', $uid); $stmtU->execute();
$user = $stmtU->get_result()->fetch_assoc(); $stmtU->close();
if (!$user) { echo "User tidak ditemukan"; exit; }

// --- Helper functions ---
function fmtJam($time){ if (!$time) return '-'; [$h,$m] = array_pad(explode(':',$time),2,0); return sprintf('%02d.%02d', (int)$h,(int)$m).' WIB'; }
function formatTanggalIdn($ymd){ $x = explode('-',$ymd); if(count($x)!=3) return ''; $bulanId = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; return (int)$x[2].' '.$bulanId[(int)$x[1]].' '.$x[0]; }
function hariIdn($ymd){ $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; return $hari[(int)date('w', strtotime($ymd))]; }
function readDocxXml($path){ $zip = new ZipArchive(); if ($zip->open($path) !== true) throw new Exception("Gagal buka $path"); $xml = $zip->getFromName('word/document.xml'); $zip->close(); if (!$xml) throw new Exception("document.xml tidak ada"); return $xml; }
function saveDocx($tplPath, $newXml, $outPath){ $tmp = tempnam(sys_get_temp_dir(), 'docx'); copy($tplPath, $tmp); $z = new ZipArchive(); if ($z->open($tmp) !== true) @unlink($tmp) && exit("zip gagal"); $z->addFromString('word/document.xml', $newXml); $z->close(); if (file_exists($outPath)) @unlink($outPath); copy($tmp, $outPath); @unlink($tmp); }
function buatCellWord($teks, $bold = false) {
    $teks = str_replace(["\r\n","\r"], "\n", $teks);
    $lines = explode("\n", $teks); $paras = ''; $b = $bold ? '<w:rPr><w:b/></w:rPr>' : '';
    foreach ($lines as $line) {
        if ($line === '') $paras .= '<w:p/>';
        else $paras .= '<w:p><w:r>'.$b.'<w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>';
    }
    return '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'.$paras.'</w:tc>';
}
function gantiSelKeduaBarisLabel(&$xml, $label, $val){
    $pattern = '#<w:tr[^>]*>(.*?<w:t[^>]*>\s*'.preg_quote($label,'#').'\s*</w:t>.*?)</w:tr>#su';
    if (!preg_match($pattern, $xml, $mRow)) return false;
    $rowOld = $mRow[0];
    if (!preg_match_all('#<w:tc[ >].*?</w:tc>#su', $rowOld, $mTc)) return false;
    $tcs = $mTc[0]; if (count($tcs) < 2) return false;
    $tc2Old = $tcs[1];
    $tc2New = preg_replace('#(<w:tc\b[^>]*><w:tcPr[^>]*>.*?</w:tcPr>)(.*?)(</w:tc>)#su', '$1<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($val, ENT_XML1).'</w:t></w:r></w:p>$3', $tc2Old);
    if ($tc2New === $tc2Old) return false;
    $rowNew = str_replace($tc2Old, $tc2New, $rowOld);
    $xml = str_replace($rowOld, $rowNew, $xml); return true;
}
function isiTtd(&$xml, $ttdTanggal, $user){
    // 1. Tanggal: [tanggal pelaksanaan] atau typo [taggal pelaksanaan]
    $xml = preg_replace_callback('#<w:t([^>]*)>(.*?)</w:t>#su', function($mm) use ($ttdTanggal, $user) {
        $t = $mm[2];
        $t = str_replace(['[tanggal pelaksanaan]','[taggal pelaksanaan]'], $ttdTanggal, $t, $count1);
        if ($t === 'NIP. Pegawai' || rtrim($t) === 'NIP. Pegawai') { $t = 'NIP. '.$user['nip']; $count1++; }
        return ($count1 > 0) ? '<w:t'.$mm[1].'>'.$t.'</w:t>' : $mm[0];
    }, $xml);
    // 2. Nama Pegawai di baris TENGAH (antara baris "Identitas Pegawai" & baris NIP.)
    if (preg_match('#<w:tr[^>]*>.*?<w:t[^>]*>\s*Identitas Pegawai\s*</w:t>.*?</w:tr>(.*?)<w:tr[^>]*>.*?NIP\.\s*Pegawai.*?</w:tr>#su', $xml, $mM)) {
        $middle = $mM[1];
        if (preg_match('#<w:tr[^>]*>.*?</w:tr>#su', $middle, $mTr)) {
            $trOld = $mTr[0];
            $trNew = preg_replace_callback('#<w:tc[ >](.*?)</w:tc>#su', function($tcm) use ($user) { static $i=0; $i++; if ($i !== 1) return $tcm[0];
                return preg_replace('#(<w:tc\b[^>]*><w:tcPr[^>]*>.*?</w:tcPr>)(.*?)(</w:tc>)#su', '$1<w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'.htmlspecialchars($user['nama'], ENT_XML1).'</w:t></w:r></w:p>$3', $tcm[0]);
            }, $trOld);
            $xml = str_replace($trOld, $trNew, $xml); return true;
        }
    }
    return false;
}

// ===========================================
// CASE 1: HARIAN
// ===========================================
if ($type === 'harian') {
    if (!$laporanId) {
        $stmt = $mysqli->prepare('SELECT id, tanggal, uraian_kegiatan, hasil, jam_mulai, jam_selesai, rhk_id FROM laporan_harian WHERE user_id=? AND MONTH(tanggal)=? AND YEAR(tanggal)=? ORDER BY tanggal DESC, id DESC LIMIT 1');
        $stmt->bind_param('iii', $uid, $bulan, $tahun); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row) { echo "Tidak ada laporan harian bulan $bulan/$tahun"; exit; }
        $laporanId = $row['id'];
    }
    $stmt = $mysqli->prepare('SELECT id, tanggal, uraian_kegiatan, hasil, jam_mulai, jam_selesai, rhk_id, jumlah_kegiatan FROM laporan_harian WHERE id=? AND user_id=? LIMIT 1');
    $stmt->bind_param('ii', $laporanId, $uid); $stmt->execute(); $lap = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$lap) { echo "Laporan $laporanId tidak ada"; exit; }
    $rhkNama = '—';
    if ($lap['rhk_id']) { $r = $mysqli->prepare('SELECT nama_rhk FROM rencana_hasil_kerja WHERE id=? LIMIT 1'); $r->bind_param('i',$lap['rhk_id']); $r->execute(); $rr = $r->get_result()->fetch_assoc(); $r->close(); if ($rr) $rhkNama=$rr['nama_rhk']; }
    $waktu = fmtJam($lap['jam_mulai']) . ' s.d. ' . fmtJam($lap['jam_selesai']);
    $ttdTanggal = formatTanggalIdn($lap['tanggal']);
    // Daftar bukti
    $stmtB = $mysqli->prepare('SELECT nama_file, path_file FROM bukti_kegiatan WHERE laporan_id=? ORDER BY id ASC');
    $stmtB->bind_param('i',$laporanId); $stmtB->execute(); $bres = $stmtB->get_result(); $buktiList = []; while($b=$bres->fetch_assoc()) $buktiList[] = $b; $stmtB->close();

    $tplPath = dirname(__DIR__, 2) . '/storage/templates/lap_harian.docx';
    if (!file_exists($tplPath)) { echo "Template lap_harian.docx tidak ada"; exit; }
    $xml = readDocxXml($tplPath);

    // 1. Identitas 4 baris
    gantiSelKeduaBarisLabel($xml, 'Nama', $user['nama']);
    gantiSelKeduaBarisLabel($xml, 'NIP', $user['nip']);
    gantiSelKeduaBarisLabel($xml, 'Jabatan', $user['jabatan']);
    gantiSelKeduaBarisLabel($xml, 'Unit Kerja', $user['unit_kerja']);

    // 2. Isi baris tabel utama No.1 (cari baris dengan <w:t>1</w:t> tepat)
    if (preg_match('#<w:tr[^>]*>(.*?<w:t[^>]*>\s*1\s*</w:t>.*?)</w:tr>#su', $xml, $mR1)) {
        $r1Old = $mR1[0];
        if (preg_match_all('#<w:tc[ >].*?</w:tc>#su', $r1Old, $mTc)) {
            $tcs = $mTc[0]; if (count($tcs) >= 6) {
                $noTc = $tcs[0];
                $tcs2 = [];
                $tcs2[0] = $noTc; // No 1 tetap
                $vals = [1 => $waktu, 2 => $lap['uraian_kegiatan'], 3 => $rhkNama, 4 => ''];
                for ($i=1; $i<=4; $i++) {
                    $tcs2[$i] = preg_replace('#(<w:tc\b[^>]*><w:tcPr[^>]*>.*?</w:tcPr>)(.*?)(</w:tc>)#su', '$1<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($vals[$i], ENT_XML1).'</w:t></w:r></w:p>$3', $tcs[$i]);
                }
                // kolom 6 = Bukti Kegiatan (list nama file & jumlah)
                $strB = ''; if (empty($buktiList)) { $strB = 'Tidak ada'; } else { $j=1; foreach($buktiList as $bb){ $strB .= ($j>1?"\n":'').$j++.'. '.$bb['nama_file']; } }
                $tcs2[5] = preg_replace('#(<w:tc\b[^>]*><w:tcPr[^>]*>.*?</w:tcPr>)(.*?)(</w:tc>)#su', '$1<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($strB, ENT_XML1).'</w:t></w:r></w:p>$3', $tcs[5]);
                $r1New = '<w:tr>'.implode('', $tcs2).'</w:tr>';
                $xml = str_replace($r1Old, $r1New, $xml);
            }
        }
    }
    // 3. TTD
    isiTtd($xml, $ttdTanggal, $user);

    // Simpan
    $folder = dirname(__DIR__, 2) . '/storage/output-word';
    if (!is_dir($folder)) { @mkdir($folder, 0777, true); }
    if (!is_dir($folder) || !is_writable($folder)) { $folder = sys_get_temp_dir(); }
    $tg = date('Ymd', strtotime($lap['tanggal']));
    $outDocx = $folder."/Laporan_Harian_ASN_{$tg}_{$laporanId}.docx";
    saveDocx($tplPath, $xml, $outDocx);

    if ($download && php_sapi_name() !== 'cli') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="'.basename($outDocx).'"');
        header('Content-Length: '.filesize($outDocx)); readfile($outDocx);
        echo "\n\nInfo: Buka file ini di Microsoft Word atau LibreOffice Writer lalu FILE -> EXPORT AS PDF untuk PDF SESUAI FORMAT WORD PERSIS.";
        exit;
    }
    echo "✅ Generate HARIAN OK: $outDocx\nUkuran: ".filesize($outDocx)." bytes\n\n";
    exit;
}

// ===========================================
// CASE 2: BULANAN
// ===========================================
else {
    $namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$bulan];
    $stmt = $mysqli->prepare('SELECT l.id, l.tanggal, l.uraian_kegiatan, l.hasil, l.jam_mulai, l.jam_selesai, l.rhk_id,
                                     r.nama_rhk, r.indikator AS uraian_rhk
                              FROM laporan_harian l
                              LEFT JOIN rencana_hasil_kerja r ON r.id=l.rhk_id
                              WHERE l.user_id=? AND MONTH(l.tanggal)=? AND YEAR(l.tanggal)=?
                              ORDER BY ISNULL(r.id), r.nama_rhk, l.tanggal, l.id ASC');
    $stmt->bind_param('iii', $uid, $bulan, $tahun); $stmt->execute(); $rows = $stmt->get_result(); $allRows = []; while ($r=$rows->fetch_assoc()) $allRows[] = $r; $stmt->close();
    $grouped = []; foreach($allRows as $rw){ $k = $rw['rhk_id'] ? 'R'.$rw['rhk_id'] : 'R0'; $grouped[$k][] = $rw; }
    // Semua bukti
    $allBukti = []; $laporanIds = array_column($allRows, 'id'); if ($laporanIds) {
        $in = implode(',', array_fill(0, count($laporanIds), '?'));
        $s = $mysqli->prepare("SELECT laporan_id, nama_file, path_file FROM bukti_kegiatan WHERE laporan_id IN ($in) ORDER BY laporan_id, id ASC");
        $types = str_repeat('i', count($laporanIds)); $s->bind_param($types, ...$laporanIds); $s->execute(); $res = $s->get_result(); while ($b=$res->fetch_assoc()) $allBukti[]=$b; $s->close();
    }
    $buktiPerLap = []; foreach($allBukti as $b) $buktiPerLap[$b['laporan_id']][] = $b;
    $tanggalTerakhir = $allRows ? $allRows[count($allRows)-1]['tanggal'] : sprintf('%04d-%02d-01',$tahun,$bulan);
    $ttdTanggal = formatTanggalIdn($tanggalTerakhir);

    $tplPath = dirname(__DIR__, 2) . '/storage/templates/lap_BULANAN.docx'; if (!file_exists($tplPath)) { echo "Template lap_BULANAN.docx tidak ada"; exit; }
    $xml = readDocxXml($tplPath);

    // 1. Identitas 4 baris
    gantiSelKeduaBarisLabel($xml, 'Nama', $user['nama']);
    gantiSelKeduaBarisLabel($xml, 'NIP', $user['nip']);
    gantiSelKeduaBarisLabel($xml, 'Jabatan', $user['jabatan']);
    gantiSelKeduaBarisLabel($xml, 'Unit Kerja', $user['unit_kerja']);

    // 2. TABEL UTAMA 5 kolom (No, RHK, Uraian Kegiatan, Waktu, Kegiatan yg Dilaksanakan)
    // Ganti seluruh tabel UTAMA (tabel pertama yang mengandung header "Kegiatan yang Dilaksanakan")
    if (preg_match('#<w:tbl\b(.*?<w:tblPr>.*?</w:tblPr>)(.*?)</w:tbl>#su', $xml, $mm)) {
        $tblFullOld = $mm[0];
        $tblPrefixAttr = $mm[1]; // <w:tbl + tblPr
        // tblPr = bagian dari $tblPrefixAttr mulai <w:tblPr> sd </w:tblPr>
        $tblPr = ''; if (preg_match('#<w:tblPr>.*?</w:tblPr>#su', $mm[1].$mm[2], $mP)) $tblPr = $mP[0];
        $tblGrid = ''; if (preg_match('#<w:tblGrid>.*?</w:tblGrid>#su', $mm[2], $mG)) $tblGrid = $mG[0];
        // Cari baris header
        $headerRow = ''; if (preg_match('#<w:tr[^>]*>.*?Kegiatan yang Dilaksanakan.*?</w:tr>#su', $tblFullOld, $mT)) $headerRow = $mT[0];
        // Buat data rows
        $dataRows = ''; $noRhk = 1;
        if (!$allRows) { $dataRows = '<w:tr>'.buatCellWord('').buatCellWord('').buatCellWord('').buatCellWord('Tidak ada kegiatan bulan ini').buatCellWord('').'</w:tr>'; }
        else foreach ($grouped as $grp) {
            $rhkNama = $grp[0]['nama_rhk'] ?? 'Tanpa RHK';
            $rhkUraian = $grp[0]['uraian_rhk'] ?? '';
            $k = 0; foreach ($grp as $rw) { $k++;
                $tcNo = buatCellWord($k === 1 ? (string)$noRhk : '');
                $tcRHK = buatCellWord($k === 1 ? $rhkNama : '', $k === 1);
                $tcUraian = buatCellWord($k === 1 ? ($rhkUraian ?: $rw['uraian_kegiatan']) : '');
                $wkt = formatTanggalIdn($rw['tanggal']) . ' · ' . fmtJam($rw['jam_mulai']) . ' s.d. ' . fmtJam($rw['jam_selesai']);
                $tcWaktu = buatCellWord($wkt);
                $kegIsi = $rw['uraian_kegiatan'];
                $tcKeg = buatCellWord($kegIsi);
                $dataRows .= '<w:tr>'.$tcNo.$tcRHK.$tcUraian.$tcWaktu.$tcKeg.'</w:tr>';
            }
            $noRhk++;
        }
        $tblFullNew = '<w:tbl>'.$tblPr.$tblGrid.$headerRow.$dataRows.'</w:tbl>';
        $xml = str_replace($tblFullOld, $tblFullNew, $xml);
    }

    // 3. TTD
    isiTtd($xml, $ttdTanggal, $user);

    // 4. TABEL LAMPIRAN (tabel terakhir, 2 kolom: Waktu Pelaksanaan | Bukti Dukung)
    if (preg_match_all('#<w:tbl\b.*?</w:tbl>#su', $xml, $mTbl)) {
        $arrTbl = $mTbl[0];
        if (count($arrTbl) >= 2) {
            $tblLampOld = $arrTbl[count($arrTbl)-1];
            $tblPr = ''; if (preg_match('#<w:tblPr>.*?</w:tblPr>#su', $tblLampOld, $mP)) $tblPr = $mP[0];
            $tblGrid = ''; if (preg_match('#<w:tblGrid>.*?</w:tblGrid>#su', $tblLampOld, $mG)) $tblGrid = $mG[0];
            $headerRow = ''; if (preg_match('#<w:tr[^>]*>.*?Waktu Pelaksanaan.*?</w:tr>#su', $tblLampOld, $mH)) $headerRow = $mH[0];
            $dataRows = '';
            foreach ($allRows as $rw) {
                $wkt = formatTanggalIdn($rw['tanggal']).' ('.hariIdn($rw['tanggal']).') '.fmtJam($rw['jam_mulai']).'-'.fmtJam($rw['jam_selesai']);
                $list = $buktiPerLap[$rw['id']] ?? [];
                if (!$list) { $dataRows .= '<w:tr>'.buatCellWord($wkt).buatCellWord('-').'</w:tr>'; continue; }
                $first = true; foreach($list as $b) {
                    $nm = $b['nama_file'];
                    $dataRows .= '<w:tr>'.buatCellWord($first ? $wkt : '').buatCellWord($nm).'</w:tr>';
                    $first = false;
                }
            }
            if (!$dataRows) $dataRows = '<w:tr>'.buatCellWord('-').buatCellWord('-').'</w:tr>';
            $tblLampNew = '<w:tbl>'.$tblPr.$tblGrid.$headerRow.$dataRows.'</w:tbl>';
            $xml = str_replace($tblLampOld, $tblLampNew, $xml);
        }
    }

    // Simpan
    $folder = dirname(__DIR__, 2) . '/storage/output-word';
    if (!is_dir($folder)) { @mkdir($folder, 0777, true); }
    if (!is_dir($folder) || !is_writable($folder)) { $folder = sys_get_temp_dir(); }
    $outDocx = $folder."/Rekap_Bulanan_ASN_{$namaBulan}_{$tahun}.docx";
    saveDocx($tplPath, $xml, $outDocx);

    if ($download && php_sapi_name() !== 'cli') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="'.basename($outDocx).'"');
        header('Content-Length: '.filesize($outDocx)); readfile($outDocx);
        echo "\n\nInfo: Buka file ini di Microsoft Word atau LibreOffice Writer lalu FILE -> EXPORT AS PDF untuk PDF SESUAI FORMAT WORD PERSIS.";
        exit;
    }
    echo "✅ Generate BULANAN OK: $outDocx\nUkuran: ".filesize($outDocx)." bytes\n";
    echo "Total laporan: ".count($allRows)." kegiatan, RHK group: ".count($grouped).", total bukti: ".count($allBukti).".\n\n";
    exit;
}

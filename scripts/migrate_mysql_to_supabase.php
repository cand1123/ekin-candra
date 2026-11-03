<?php
/**
 * Skrip Migrasi Data dari MySQL ke Supabase REST API
 * Menjaga urutan relasi foreign key:
 *   1. users
 *   2. rencana_hasil_kerja
 *   3. laporan_harian
 *   4. rencana_aksi
 *   5. rhk_bulanan_capaian
 *   6. bukti_kegiatan
 */

// 1. Baca file .env
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die("File .env tidak ditemukan di root proyek!\n");
}

$env = parse_ini_file($envFile);
$supabaseUrl = $env['SUPABASE_URL'] ?? null;
$supabaseKey = $env['SUPABASE_SECRET_KEY'] ?? null;

if (!$supabaseUrl || !$supabaseKey) {
    die("SUPABASE_URL atau SUPABASE_SECRET_KEY tidak ditemukan di .env!\n");
}

// 2. Hubungkan ke MySQL
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'laporan_asn';

echo "=== MIGRASI DATA DARI MYSQL KE SUPABASE ===\n";
echo "Menghubungkan ke MySQL ($host / $dbname)...\n";

$mysqli = @new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Gagal terhubung ke MySQL: " . $mysqli->connect_error . "\nPastikan Laragon (MySQL) sudah dinyalakan!\n");
}
$mysqli->set_charset('utf8mb4');
echo "Berhasil terhubung ke MySQL!\n\n";

// Fungsi helper kirim data ke Supabase REST API
function uploadToSupabase($table, $rows, $supabaseUrl, $supabaseKey) {
    if (empty($rows)) return true;

    $url = rtrim($supabaseUrl, '/') . '/rest/v1/' . $table;
    $ch = curl_init($url);
    $payload = json_encode($rows);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Prefer: resolution=merge-duplicates' // Upsert otomatis jika data sudah ada
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        echo "  [ERROR] Upload ke '$table' gagal (HTTP $httpCode): $response $error\n";
        return false;
    }
}

// Urutan tabel untuk menjaga integritas foreign key
$tables = [
    'users' => 'id',
    'rencana_hasil_kerja' => 'id',
    'laporan_harian' => 'id',
    'rencana_aksi' => 'id',
    'rhk_bulanan_capaian' => null,
    'bukti_kegiatan' => 'id'
];

foreach ($tables as $table => $idCol) {
    echo "Memproses tabel: `$table`...\n";
    
    // Periksa apakah tabel ada di MySQL
    $check = $mysqli->query("SHOW TABLES LIKE '$table'");
    if (!$check || $check->num_rows === 0) {
        echo "  [SKIP] Tabel `$table` tidak ditemukan di MySQL.\n";
        continue;
    }

    $res = $mysqli->query("SELECT * FROM `$table`");
    $total = $res->num_rows;
    echo "  Total data ditemukan: $total baris.\n";

    if ($total === 0) {
        continue;
    }

    $batch = [];
    $count = 0;
    $successCount = 0;

    while ($row = $res->fetch_assoc()) {
        // Normalisasi null & tipe data untuk JSON/Postgres
        foreach ($row as $k => $v) {
            if ($v === null) continue;
            // Kolom numerik boolean/tinyint
            if (in_array($k, ['bulan', 'tahun', 'jumlah_kegiatan', 'ukuran_file', 'user_id', 'rhk_id', 'laporan_id', 'id'])) {
                if ($v !== '') $row[$k] = (int)$v;
            }
        }
        $batch[] = $row;
        $count++;

        if (count($batch) >= 100) {
            if (uploadToSupabase($table, $batch, $supabaseUrl, $supabaseKey)) {
                $successCount += count($batch);
            }
            $batch = [];
        }
    }

    if (!empty($batch)) {
        if (uploadToSupabase($table, $batch, $supabaseUrl, $supabaseKey)) {
            $successCount += count($batch);
        }
    }

    echo "  Selesai: $successCount / $total baris berhasil dimigrasikan ke Supabase.\n\n";
}

echo "=== MIGRASI SELESAI ===\n";


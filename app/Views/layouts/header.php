<?php
require_once __DIR__ . '/../../helpers/bootstrap.php';
require_login();

$uid = current_user_id();
$stmt = $mysqli->prepare('SELECT nama, nip, jabatan, foto FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$currUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currPage = basename($_SERVER['PHP_SELF']);
$_namaUser = e($currUser['nama'] ?? 'User');
$_inisialUser = get_inisial($_namaUser);
$_jabatan = e($currUser['jabatan'] ?? 'ASN');

$_activePage = function ($p) use ($currPage) {
    if (is_array($p)) return in_array($currPage, $p, true) ? 'active' : '';
    return $currPage === $p ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Sistem Laporan ASN' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/legacy.css?v=<?= time() ?>" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    <link href="assets/css/responsive.css?v=<?= time() ?>" rel="stylesheet">
    <link href="assets/css/pdf-preview.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES, 'UTF-8') ?>">
<script>
    // Terapkan status sembunyi sidebar sebelum render agar tidak berkedip
    (function () {
        try {
            if (localStorage.getItem('asnSidebarHidden') === '1') {
                document.body.classList.add('sidebar-hidden');
            }
        } catch (e) {}
    })();
</script>

<div class="app-wrapper" id="appWrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebarNav" aria-label="Sidebar Navigasi">
        <div class="sidebar__brand">
            <div class="sidebar__brand-title">Sistem Laporan ASN</div>
            <div class="sidebar__brand-sub">Pelaporan Kinerja Harian</div>
        </div>
        <div class="sidebar-section-label">Menu Utama</div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="nav-link <?= $_activePage('dashboard.php') ?>">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="rekap_bulanan.php" class="nav-link <?= $_activePage(['rekap_bulanan.php', 'cetak_pdf.php']) ?>">
                    <i class="bi bi-calendar-month"></i>
                    <span>Rekap Kegiatan</span>
                </a>
            </li>
            <li>
                <a href="laporan_harian.php" class="nav-link <?= $_activePage(['laporan_harian.php', 'edit_laporan.php', 'simpan_laporan.php', 'cetak_laporan_harian.php']) ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Kegiatan</span>
                </a>
            </li>
            <li>
                <a href="perjadin.php" class="nav-link <?= $_activePage('perjadin.php') ?>">
                    <i class="bi bi-signpost-2"></i>
                    <span>Perjalanan Dinas</span>
                </a>
            </li>
            <li>
                <a href="rhk.php" class="nav-link <?= $_activePage('rhk.php') ?>">
                    <i class="bi bi-list-check"></i>
                    <span>Rencana Aksi</span>
                </a>
            </li>
            <li>
                <a href="pengaturan.php" class="nav-link <?= $_activePage('pengaturan.php') ?>">
                    <i class="bi bi-gear"></i>
                    <span>Setting RHK</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="nav-link" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar</span>
                </a>
            </li>
        </ul>
    </aside>

    <div id="sidebarBackdrop"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="topbar-toggle" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <button type="button" class="topbar-hide" id="sidebarHideBtn" aria-label="Sembunyikan Sidebar" title="Sembunyikan Sidebar">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            </div>
            <div class="topbar-right">
                <button type="button" class="topbar-notif" aria-label="Notifikasi">
                    <i class="bi bi-bell"></i>
                    <span class="notif-dot" aria-hidden="true"></span>
                </button>
                <div class="dropdown">
                    <button type="button" class="topbar-user" data-bs-toggle="dropdown" aria-expanded="false" id="userDropdownBtn">
                        <div class="avatar" aria-hidden="true"><?= $_inisialUser ?></div>
                        <div class="user-text">
                            <strong><?= $_namaUser ?></strong>
                            <small><?= $_jabatan ?></small>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdownBtn">
                        <li>
                            <a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person-gear"></i>
                                <span>Profil Saya</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Keluar</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="page-content" id="pageContent">

<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    $sessPath = session_save_path();
    if ($sessPath === '' || stripos($sessPath, 'laragon') !== false || !is_dir($sessPath) || !is_writable($sessPath)) {
        $tmpDir = sys_get_temp_dir();
        if (is_dir($tmpDir) && is_writable($tmpDir)) {
            session_save_path($tmpDir);
        }
    }
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

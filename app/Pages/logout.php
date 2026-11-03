<?php
require_once dirname(__DIR__) . '/helpers/session.php';

$_SESSION = [];
session_destroy();

$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
);

header('Location: login.php');
exit;

<?php
require_once dirname(__DIR__) . '/helpers/session.php';

asn_end_user_session();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: login.php');
exit;

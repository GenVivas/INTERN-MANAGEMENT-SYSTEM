<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/audit.php';

if (isset($_SESSION['user_id'])) {
    logAudit('LOGOUT', 'Auth', (int)$_SESSION['user_id'], ($_SESSION['user_name'] ?? '') . ' logged out.');
}

session_unset();
session_destroy();
header('Location: /login.php');
exit;

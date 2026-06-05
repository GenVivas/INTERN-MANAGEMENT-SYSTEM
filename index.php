<?php
// Entry point — redirect to dashboard or login
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;

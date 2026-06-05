<?php
// ============================================================
// Session Configuration & Helpers
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

define('SESSION_TIMEOUT', 1800); // 30 minutes

function checkSession(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    // Timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireRole(string $role): void {
    checkSession();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: /dashboard.php');
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserName(): string {
    return $_SESSION['user_name'] ?? '';
}

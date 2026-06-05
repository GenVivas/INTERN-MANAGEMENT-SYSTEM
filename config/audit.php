<?php
require_once __DIR__ . '/db.php';

function logAudit(string $action, string $module, ?int $recordId, string $description): void {
    $db       = getDB();
    $userId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $userName = $_SESSION['user_name'] ?? 'System';

    $stmt = $db->prepare(
        "INSERT INTO audit_trail (user_id, user_name, action, module, record_id, description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssss', $userId, $userName, $action, $module, $recordId, $description);
    $stmt->execute();
    $stmt->close();
}

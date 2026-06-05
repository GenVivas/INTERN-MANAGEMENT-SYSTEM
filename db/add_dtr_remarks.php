<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$r = $db->query("SHOW COLUMNS FROM dtr_entries LIKE 'remarks'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE dtr_entries ADD COLUMN remarks VARCHAR(50) DEFAULT NULL AFTER undertime");
    echo "Added: remarks column\n";
} else {
    echo "Already exists: remarks\n";
}
echo "Done.\n";

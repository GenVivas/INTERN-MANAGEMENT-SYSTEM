<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$cols = [
    'day_type' => "ENUM('Regular','Holiday','Absent','Half-Day','Excused') NOT NULL DEFAULT 'Regular'",
    'remarks'  => "VARCHAR(255) DEFAULT NULL",
];

foreach ($cols as $name => $def) {
    $r = $db->query("SHOW COLUMNS FROM dtr_entries LIKE '{$name}'");
    if ($r->num_rows === 0) {
        $db->query("ALTER TABLE dtr_entries ADD COLUMN {$name} {$def}");
        echo "Added: {$name}\n";
    } else {
        echo "Exists: {$name}\n";
    }
}
echo "Done.\n";

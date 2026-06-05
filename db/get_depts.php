<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$r  = $db->query('SELECT id, name FROM departments ORDER BY name');
while ($row = $r->fetch_assoc()) {
    echo $row['id'] . ' = ' . $row['name'] . "\n";
}

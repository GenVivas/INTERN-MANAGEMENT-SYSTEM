<?php
require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Get department IDs
$depts = [];
$r = $db->query("SELECT id, name FROM departments");
while ($row = $r->fetch_assoc()) {
    $depts[$row['name']] = $row['id'];
}

echo "Departments found:\n";
foreach ($depts as $name => $id) echo "  [{$id}] {$name}\n";
echo "\n";

// ── Interns to insert ────────────────────────────────────────────────────────
// Format: [first_name, last_name, middle_name, department]
$interns = [
    // Business Development
    ['Ronan Aleck',   'Gatmaitan',  'Acab',      'Business Development'],
    ['Mark Justin',   'Aguilar',    'Bansag',     'Business Development'],
    ['John Asher',    'Manit',      'Melliza',    'Business Development'],
    ['Rex',           'Parafina',   'Cabiles',    'Business Development'],
    ['Rain Louie',    'Robles',     '',           'Business Development'],
    ['Keith',         'Antonio',    'Marquez',    'Business Development'],
    ['Kaye Cee',      'Castro',     'Villanueva', 'Business Development'],
    ['Kristrayah',    'Sison',      'Maturan',    'Business Development'],
    ['Noreen',        'Sarmiento',  'Reyes',      'Business Development'],
    ['Lala Elaine',   'Caleon',     'Marcelo',    'Business Development'],

    // Sales and Marketing
    ['Christian Jay', 'Barcos',     'P.',         'Sales and Marketing'],
    ['Glenn Jim',     'Gamul',      'G.',         'Sales and Marketing'],
];

$inserted = 0;
$skipped  = 0;

foreach ($interns as $intern) {
    [$fn, $ln, $mn, $deptName] = $intern;

    if (!isset($depts[$deptName])) {
        echo "⚠ Department not found: {$deptName} — skipping {$fn} {$ln}\n";
        $skipped++;
        continue;
    }

    $deptId = $depts[$deptName];

    // Check for duplicate
    $chk = $db->prepare("SELECT id FROM interns WHERE first_name=? AND last_name=? AND department_id=?");
    $chk->bind_param('ssi', $fn, $ln, $deptId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($exists) {
        echo "↷ Already exists: {$fn} {$ln} ({$deptName}) — skipped\n";
        $skipped++;
        continue;
    }

    $stmt = $db->prepare(
        "INSERT INTO interns (department_id, first_name, last_name, middle_name, required_hours)
         VALUES (?, ?, ?, ?, 486)"
    );
    $stmt->bind_param('isss', $deptId, $fn, $ln, $mn);
    $stmt->execute();
    $newId = $db->insert_id;
    $stmt->close();

    echo "✔ Inserted [{$newId}] {$fn} {$ln} → {$deptName}\n";
    $inserted++;
}

echo "\nDone. Inserted: {$inserted} | Skipped: {$skipped}\n";

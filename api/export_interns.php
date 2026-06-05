<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
checkSession();

$db     = getDB();
$format = strtolower($_GET['format'] ?? 'csv');

$interns = $db->query(
    "SELECT i.first_name, i.last_name, i.email, i.phone, i.school, i.course,
            i.year_level, i.status, i.required_hours, i.rendered_hours,
            i.start_date, i.end_date, i.supervisor, d.name AS dept_name
     FROM interns i
     JOIN departments d ON d.id = i.department_id
     ORDER BY d.name, i.last_name, i.first_name"
)->fetch_all(MYSQLI_ASSOC);

$filename = 'Interns_TDTPowersteel_' . date('Ymd');

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['TDT Powersteel Corp. — Intern List']);
    fputcsv($out, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['Last Name','First Name','Email','Phone','Department','School','Course',
                   'Year Level','Status','Required Hrs','Rendered Hrs','Remaining Hrs',
                   'Start Date','End Date','Supervisor']);
    foreach ($interns as $i) {
        fputcsv($out, [
            $i['last_name'], $i['first_name'], $i['email'], $i['phone'],
            $i['dept_name'], $i['school'], $i['course'], $i['year_level'],
            $i['status'],
            number_format($i['required_hours'],0),
            number_format($i['rendered_hours'],2),
            number_format(max(0,$i['required_hours']-$i['rendered_hours']),2),
            $i['start_date'], $i['end_date'], $i['supervisor'],
        ]);
    }
    fclose($out);
    exit;
}

// PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Intern List — TDT Powersteel</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; margin: 20px; }
  h2 { color: #E8621A; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: #E8621A; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
  td { padding: 5px 8px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #fafafa; }
  .badge { display:inline-block;padding:2px 7px;border-radius:10px;font-size:9px;font-weight:700; }
  .badge-active   { background:#ECFDF5;color:#16A34A; }
  .badge-archived { background:#F3F4F6;color:#6B7280; }
  @media print { button { display:none; } }
</style>
</head>
<body>
<h2>TDT Powersteel Corp. — Intern List</h2>
<p style="color:#555">Generated: <?= date('F d, Y h:i A') ?> &nbsp;|&nbsp; Total: <?= count($interns) ?> interns</p>
<button onclick="window.print()" style="background:#E8621A;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;margin-bottom:12px">
    🖨 Print / Save as PDF
</button>
<table>
    <thead>
        <tr>
            <th>#</th><th>Name</th><th>Department</th><th>School</th>
            <th>Status</th><th>Required</th><th>Rendered</th><th>Remaining</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($interns as $i => $intern): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($intern['last_name'].', '.$intern['first_name']) ?></td>
        <td><?= htmlspecialchars($intern['dept_name']) ?></td>
        <td><?= htmlspecialchars($intern['school'] ?? '—') ?></td>
        <td><span class="badge badge-<?= strtolower($intern['status']) ?>"><?= $intern['status'] ?></span></td>
        <td><?= number_format($intern['required_hours'],0) ?></td>
        <td><?= number_format($intern['rendered_hours'],2) ?></td>
        <td><?= number_format(max(0,$intern['required_hours']-$intern['rendered_hours']),2) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>

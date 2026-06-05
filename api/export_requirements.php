<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
checkSession();

$db       = getDB();
$internId = (int)($_GET['intern_id'] ?? 0);

$stmt = $db->prepare("SELECT i.*, d.name AS dept_name FROM interns i JOIN departments d ON d.id=i.department_id WHERE i.id=?");
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$intern) { http_response_code(404); exit('Intern not found.'); }

$stmt = $db->prepare("SELECT * FROM requirement_items WHERE intern_id=? AND is_archived=0 ORDER BY created_at ASC");
$stmt->bind_param('i', $internId);
$stmt->execute();
$reqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];
$approved  = count(array_filter($reqs, fn($r) => $r['status'] === 'Approved'));
$submitted = count(array_filter($reqs, fn($r) => $r['status'] === 'Submitted'));
$pending   = count(array_filter($reqs, fn($r) => $r['status'] === 'Pending'));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Requirements — <?= htmlspecialchars($fullName) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 20px; }
  h2 { color: #E8621A; margin-bottom: 4px; }
  .meta { margin-bottom: 16px; color: #555; }
  .summary { display: flex; gap: 20px; margin-bottom: 16px; }
  .summary-item { background: #f9f9f9; border: 1px solid #eee; border-radius: 8px; padding: 10px 16px; text-align: center; }
  .summary-item .val { font-size: 22px; font-weight: 700; }
  .summary-item .lbl { font-size: 11px; color: #888; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: #E8621A; color: #fff; padding: 7px 10px; text-align: left; font-size: 11px; }
  td { padding: 7px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
  tr:nth-child(even) td { background: #fafafa; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
  .badge-approved  { background: #ECFDF5; color: #16A34A; }
  .badge-submitted { background: #EFF6FF; color: #2563EB; }
  .badge-pending   { background: #FFFBEB; color: #D97706; }
  @media print { button { display: none; } }
</style>
</head>
<body>
<h2>TDT Powersteel Corp.</h2>
<h3 style="margin:0 0 8px">Requirements Monitoring Report</h3>
<div class="meta">
    <strong>Intern:</strong> <?= htmlspecialchars($fullName) ?> &nbsp;|&nbsp;
    <strong>Department:</strong> <?= htmlspecialchars($intern['dept_name']) ?> &nbsp;|&nbsp;
    <strong>School:</strong> <?= htmlspecialchars($intern['school'] ?? '—') ?><br>
    <strong>Generated:</strong> <?= date('F d, Y h:i A') ?>
</div>

<div class="summary">
    <div class="summary-item"><div class="val" style="color:#16A34A"><?= $approved ?></div><div class="lbl">Approved</div></div>
    <div class="summary-item"><div class="val" style="color:#2563EB"><?= $submitted ?></div><div class="lbl">Submitted</div></div>
    <div class="summary-item"><div class="val" style="color:#D97706"><?= $pending ?></div><div class="lbl">Pending</div></div>
    <div class="summary-item"><div class="val"><?= count($reqs) ?></div><div class="lbl">Total</div></div>
</div>

<button onclick="window.print()" style="background:#E8621A;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;margin-bottom:12px">
    🖨 Print / Save as PDF
</button>

<table>
    <thead>
        <tr><th>#</th><th>Requirement</th><th>Status</th><th>Date Submitted</th><th>Remarks</th></tr>
    </thead>
    <tbody>
    <?php foreach ($reqs as $i => $r): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
        <td><?= $r['submission_date'] ?? '—' ?></td>
        <td><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>

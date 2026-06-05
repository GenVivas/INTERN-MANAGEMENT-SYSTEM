<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
checkSession();

$db       = getDB();
$internId = (int)($_GET['intern_id'] ?? 0);
$format   = strtolower($_GET['format'] ?? 'pdf');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$stmt = $db->prepare(
    "SELECT i.*, d.name AS dept_name FROM interns i
     JOIN departments d ON d.id = i.department_id WHERE i.id = ?"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$intern) { http_response_code(404); exit('Intern not found.'); }

$where  = "WHERE intern_id=? AND is_archived=0";
$params = [$internId]; $types = 'i';
if ($dateFrom) { $where .= " AND entry_date >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo)   { $where .= " AND entry_date <= ?"; $params[] = $dateTo;   $types .= 's'; }

$stmt = $db->prepare("SELECT * FROM dtr_entries {$where} ORDER BY entry_date ASC, id ASC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fullName  = $intern['first_name'] . ' ' . $intern['last_name'];
$remaining = max(0, $intern['required_hours'] - $intern['rendered_hours']);
$filename  = 'DTR_' . str_replace(' ', '_', $fullName) . '_' . date('Ymd');

// ── CSV export ────────────────────────────────────────────────────────────────
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['TDT Powersteel Corp. — Daily Time Record']);
    fputcsv($out, ['Intern:', $fullName, 'Department:', $intern['dept_name']]);
    fputcsv($out, ['School:', $intern['school'] ?? '']);
    fputcsv($out, ['Required Hours:', $intern['required_hours'], 'Rendered:', $intern['rendered_hours'], 'Remaining:', $remaining]);
    fputcsv($out, ['Generated:', date('F d, Y h:i A')]);
    fputcsv($out, []);
    fputcsv($out, ['#', 'Date', 'Time In', 'Time Out', 'Rendered Hrs', 'Overtime', 'Remarks']);
    $noTime = ['Absent','Holiday','No Office','Excused'];
    foreach ($entries as $i => $e) {
        $isNoTime = in_array($e['remarks'] ?? '', $noTime);
        fputcsv($out, [
            $i + 1,
            $e['entry_date'],
            $isNoTime ? '—' : ($e['time_in']  ?? ''),
            $isNoTime ? '—' : ($e['time_out'] ?? ''),
            $isNoTime ? '0.00' : number_format($e['rendered_hours'], 2),
            $isNoTime ? '0.00' : number_format($e['overtime'],       2),
            $e['remarks'] ?? '',
        ]);
    }
    fputcsv($out, []);
    $totalRendered = array_sum(array_map(fn($e) => in_array($e['remarks']??'', $noTime) ? 0 : $e['rendered_hours'], $entries));
    fputcsv($out, ['', '', '', 'TOTAL', number_format($totalRendered, 2)]);
    fclose($out);
    exit;
}

// ── PDF (HTML print) ─────────────────────────────────────────────────────────
$noTime = ['Absent','Holiday','No Office','Excused'];
$totalRendered = array_sum(array_map(fn($e) => in_array($e['remarks']??'', $noTime) ? 0 : $e['rendered_hours'], $entries));
$totalOT       = array_sum(array_map(fn($e) => in_array($e['remarks']??'', $noTime) ? 0 : $e['overtime'],       $entries));
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>DTR — <?= htmlspecialchars($fullName) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #222; background: #fff; }

  /* ── Letterhead ── */
  .letterhead {
      padding: 24px 40px 0;
      border-bottom: 3px solid #E8621A;
  }
  .letterhead-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 14px;
  }
  .letterhead img.logo-img {
      height: 70px;
      width: auto;
  }
  .company-info { text-align: right; font-size: 9px; color: #888; line-height: 1.6; }

  /* ── Content ── */
  .content { padding: 24px 40px; }

  .doc-title {
      font-size: 17px;
      font-weight: 700;
      color: #222;
      margin-bottom: 14px;
      text-transform: uppercase;
      letter-spacing: 1px;
      text-align: center;
  }

  .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 20px; margin-bottom: 16px; font-size: 10.5px; }
  .meta-item { display: flex; gap: 4px; }
  .meta-label { font-weight: 700; color: #555; white-space: nowrap; }
  .meta-value { color: #222; }

  .meta-divider { border: none; border-top: 1px solid #E2E4E8; margin: 12px 0; }

  /* ── Table ── */
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
  thead th {
      background: #E8621A; color: #fff;
      padding: 7px 10px; text-align: left;
      font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px;
  }
  tbody td { padding: 6px 10px; border-bottom: 1px solid #F0F0F0; }
  tbody tr:nth-child(even) td { background: #FAFAFA; }
  .remark-badge {
      display: inline-block; padding: 1px 7px; border-radius: 10px;
      font-size: 9px; font-weight: 700;
  }
  .remark-absent   { background: #FEF2F2; color: #DC2626; }
  .remark-holiday  { background: #ECFDF5; color: #16A34A; }
  .remark-excused  { background: #EFF6FF; color: #2563EB; }
  .remark-halfday  { background: #FFFBEB; color: #D97706; }
  .remark-nooffice { background: #F3F4F6; color: #6B7280; }

  .total-row td { font-weight: 700; background: #FFF7F3 !important; border-top: 2px solid #E8621A; }

  /* ── Footer ── */
  .letterfoot {
      margin-top: 40px;
      padding: 10px 40px;
      border-top: 2px solid #E8621A;
      text-align: center;
      font-size: 8.5px; color: #999;
  }

  .print-btn {
      display: block; margin: 16px auto 0;
      background: #E8621A; color: #fff; border: none;
      padding: 9px 22px; border-radius: 6px;
      font-size: 12px; font-weight: 600; cursor: pointer;
  }

  @media print {
      .print-btn { display: none; }
      body { font-size: 10px; }
  }
</style>
</head>
<body>

<!-- Letterhead -->
<div class="letterhead">
    <div class="letterhead-top">
        <img src="/uploads/photos/logo-light.jpg" alt="TDT Powersteel" class="logo-img">
        <div class="company-info">
            1017 – A. Vicente Cruz St., Sampaloc, Zone 047, Brgy. 475, Manila<br>
            Tel. No. (02) 8 831-0000<br>
            www.powersteel.com.ph
        </div>
    </div>
</div>

<!-- Content -->
<div class="content">
    <button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    <br><br>
    <div class="doc-title">Daily Time Record</div>

    <div class="meta-grid">
        <div class="meta-item"><span class="meta-label">Intern:</span><span class="meta-value"><?= htmlspecialchars($fullName) ?></span></div>
        <div class="meta-item"><span class="meta-label">Department:</span><span class="meta-value"><?= htmlspecialchars($intern['dept_name']) ?></span></div>
        <div class="meta-item"><span class="meta-label">School:</span><span class="meta-value"><?= htmlspecialchars($intern['school'] ?? '—') ?></span></div>
        <div class="meta-item"><span class="meta-label">Course:</span><span class="meta-value"><?= htmlspecialchars($intern['course'] ?? '—') ?></span></div>
        <div class="meta-item"><span class="meta-label">Required Hours:</span><span class="meta-value"><?= number_format($intern['required_hours'], 0) ?></span></div>
        <div class="meta-item"><span class="meta-label">Rendered Hours:</span><span class="meta-value"><?= number_format($intern['rendered_hours'], 2) ?></span></div>
        <div class="meta-item"><span class="meta-label">Remaining Hours:</span><span class="meta-value"><?= number_format($remaining, 2) ?></span></div>
        <div class="meta-item"><span class="meta-label">Generated:</span><span class="meta-value"><?= date('F d, Y h:i A') ?></span></div>
        <?php if ($dateFrom || $dateTo): ?>
        <div class="meta-item"><span class="meta-label">Period:</span><span class="meta-value"><?= $dateFrom ?: '—' ?> to <?= $dateTo ?: '—' ?></span></div>
        <?php endif; ?>
    </div>

    <hr class="meta-divider">

    <table>
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Rendered Hrs</th>
                <th>Overtime</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $i => $e):
            $isNoTime = in_array($e['remarks'] ?? '', $noTime);
            $remarkClass = match($e['remarks'] ?? '') {
                'Absent'    => 'remark-absent',
                'Holiday'   => 'remark-holiday',
                'Excused'   => 'remark-excused',
                'Half Day'  => 'remark-halfday',
                'No Office' => 'remark-nooffice',
                default     => ''
            };
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($e['entry_date']) ?></td>
            <td><?= $isNoTime ? '<span style="color:#aaa">—</span>' : htmlspecialchars($e['time_in'] ?? '—') ?></td>
            <td><?= $isNoTime ? '<span style="color:#aaa">—</span>' : htmlspecialchars($e['time_out'] ?? '—') ?></td>
            <td><?= $isNoTime ? '0.00' : number_format($e['rendered_hours'], 2) ?></td>
            <td><?= $isNoTime ? '0.00' : number_format($e['overtime'], 2) ?></td>
            <td>
                <?php if ($e['remarks']): ?>
                <span class="remark-badge <?= $remarkClass ?>"><?= htmlspecialchars($e['remarks']) ?></span>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="4" style="text-align:right;padding-right:14px">TOTAL</td>
            <td><?= number_format($totalRendered, 2) ?></td>
            <td><?= number_format($totalOT, 2) ?></td>
            <td></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- Footer -->
<div class="letterfoot">
    1017 – A. Vicente Cruz St., Sampaloc, Zone 047, Brgy. 475, Manila &nbsp;|&nbsp;
    Tel. No. (02) 8 831-0000 &nbsp;|&nbsp; www.powersteel.com.ph
</div>

</body>
</html>

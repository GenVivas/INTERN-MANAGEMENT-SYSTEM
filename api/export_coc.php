<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';
checkSession();

$db       = getDB();
$internId = (int)($_GET['intern_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT i.*, d.name AS dept_name FROM interns i
     JOIN departments d ON d.id = i.department_id WHERE i.id = ?"
);
$stmt->bind_param('i', $internId);
$stmt->execute();
$intern = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$intern) { http_response_code(404); exit('Intern not found.'); }

$fullName  = strtoupper($intern['first_name'] . ' ' . ($intern['middle_name'] ? $intern['middle_name'].' ' : '') . $intern['last_name']);
$lastName  = strtoupper($intern['last_name']);
$hours     = number_format($intern['required_hours'], 0);
$startDate = $intern['start_date'] ? date('F j, Y', strtotime($intern['start_date'])) : '___________';
$endDate   = $intern['end_date']   ? date('F j, Y', strtotime($intern['end_date']))   : '___________';

// Day with ordinal suffix
$day      = $intern['end_date'] ? date('j', strtotime($intern['end_date'])) : '___';
$month    = $intern['end_date'] ? date('F Y', strtotime($intern['end_date'])) : '______';
$ordinal  = match(true) {
    $day % 100 >= 11 && $day % 100 <= 13 => $day.'th',
    $day % 10 === 1 => $day.'st',
    $day % 10 === 2 => $day.'nd',
    $day % 10 === 3 => $day.'rd',
    default         => $day.'th'
};

// Gender pronoun
$pronoun = match($intern['gender'] ?? '') {
    'Female' => 'her',
    default  => 'his',
};

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Certificate of Completion — <?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
      font-family: 'Times New Roman', Times, serif;
      background: #fff;
      color: #222;
  }

  .page {
      width: 816px;
      min-height: 1056px;
      margin: 0 auto;
      padding: 0;
      display: flex;
      flex-direction: column;
      position: relative;
      border: 1px solid #ddd;
  }

  /* ── Header ── */
  .cert-header {
      padding: 28px 60px 16px;
      border-bottom: 3px solid #E8621A;
  }
  .cert-header img.logo-img {
      height: 70px;
      width: auto;
      display: block;
  }

  /* ── Body ── */
  .cert-body {
      flex: 1;
      padding: 50px 80px 40px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
  }

  .cert-title {
      font-family: Arial, sans-serif;
      font-size: 26px;
      font-weight: 900;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: #222;
      margin-bottom: 30px;
  }

  .cert-certify {
      font-size: 13px;
      color: #555;
      margin-bottom: 18px;
      font-style: italic;
  }

  .cert-name {
      font-size: 24px;
      font-weight: 700;
      text-decoration: underline;
      letter-spacing: 1px;
      margin-bottom: 20px;
      color: #111;
  }

  .cert-body-text {
      font-size: 13px;
      line-height: 1.9;
      color: #333;
      max-width: 580px;
      text-align: center;
  }

  .cert-company {
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 1px;
  }

  .cert-request {
      font-size: 12px;
      color: #444;
      margin-top: 24px;
      line-height: 1.8;
  }

  .cert-given {
      font-size: 12px;
      color: #444;
      margin-top: 14px;
  }

  /* ── Signature ── */
  .cert-signature {
      margin-top: 60px;
      text-align: center;
  }
  .sig-name  { font-size: 14px; font-weight: 700; }
  .sig-title { font-size: 11px; font-style: italic; color: #555; line-height: 1.7; }

  .cert-watermark {
      font-size: 9.5px;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #999;
      margin-top: 30px;
      font-family: Arial, sans-serif;
  }

  /* ── Footer ── */
  .cert-footer {
      padding: 12px 60px;
      border-top: 2px solid #E8621A;
      text-align: center;
      font-family: Arial, sans-serif;
      font-size: 8.5px;
      color: #999;
      line-height: 1.7;
  }

  .print-btn {
      display: block; margin: 20px auto;
      background: #E8621A; color: #fff; border: none;
      padding: 10px 24px; border-radius: 6px;
      font-size: 13px; font-weight: 600; cursor: pointer;
      font-family: Arial, sans-serif;
  }

  @media print {
      .print-btn { display: none !important; }
      body { margin: 0; }
      .page { border: none; }
  }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Print / Save as PDF</button>

<div class="page">

    <!-- Header -->
    <div class="cert-header">
        <img src="/uploads/photos/logo-light.jpg" alt="TDT Powersteel" class="logo-img">
    </div>

    <!-- Body -->
    <div class="cert-body">

        <div class="cert-title">Certificate of Completion</div>

        <div class="cert-certify">This is to certify that</div>

        <div class="cert-name"><?= htmlspecialchars($fullName) ?></div>

        <div class="cert-body-text">
            has completed <?= $pronoun ?> internship program with total hours of
            <strong><?= $hours ?> hours</strong> at<br>
            <span class="cert-company">TDT POWERSTEEL CORP.</span>
        </div>

        <br>

        <div class="cert-body-text">
            from <u><?= $startDate ?></u> to <u><?= $endDate ?></u>
        </div>

        <div class="cert-request">
            This certification is being issued upon request of
            <strong>Mr./Ms. <?= htmlspecialchars($lastName) ?></strong>
            for academic purposes only.
        </div>

        <div class="cert-given">
            Given this <?= $ordinal ?> day of <?= $month ?> at Sampaloc, Manila.
        </div>

        <!-- Signature -->
        <div class="cert-signature">
            <div class="sig-name">Monaliza R. Acuña, CPA, MIRS|</div>
            <div class="sig-title">
                AVP for Finance and Accounting<br>
                HR &amp; Admin Officer-in-charge
            </div>
        </div>

        <div class="cert-watermark">NOT VALID WITHOUT THE SIGN OF IMMEDIATE HEAD</div>

    </div>

    <!-- Footer -->
    <div class="cert-footer">
        1017 – A. Vicente Cruz St., Sampaloc, Zone 047, Brgy. 475, Manila<br>
        Tel. No. (02) 8 831-0000 &nbsp;&nbsp; www.powersteel.com.ph
    </div>

</div>

</body>
</html>

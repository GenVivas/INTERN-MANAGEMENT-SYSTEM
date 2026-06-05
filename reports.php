<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

$db = getDB();

$depts = $db->query(
    "SELECT d.id, d.name,
            COUNT(CASE WHEN i.status='Active' THEN 1 END) AS active_count,
            COALESCE(SUM(CASE WHEN i.status='Active' THEN i.rendered_hours END),0) AS total_rendered,
            COALESCE(SUM(CASE WHEN i.status='Active' THEN i.required_hours END),0) AS total_required
     FROM departments d
     LEFT JOIN interns i ON i.department_id = d.id
     GROUP BY d.id, d.name
     ORDER BY d.name ASC"
)->fetch_all(MYSQLI_ASSOC);

$interns = $db->query(
    "SELECT i.id, i.first_name, i.last_name, i.rendered_hours, i.required_hours,
            i.status, i.start_date, i.end_date, d.name AS dept_name
     FROM interns i
     JOIN departments d ON d.id = i.department_id
     ORDER BY d.name, i.last_name, i.first_name"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Reports & Export';
$breadcrumbs = [['label' => 'Reports & Export', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Reports &amp; Export</h1>
    <p>Generate and export reports for interns, DTR records, and requirements.</p>
</div>

<!-- Quick Export Cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));margin-bottom:28px">
    <div class="card" style="padding:20px">
        <div class="d-flex align-center gap-12 mb-16">
            <div class="stat-icon orange"><i class="fas fa-users"></i></div>
            <div>
                <div class="fw-600">All Interns</div>
                <div class="fs-12 text-muted">Full intern list with hours</div>
            </div>
        </div>
        <div class="d-flex gap-8">
            <a href="/api/export_interns.php?format=pdf" target="_blank" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="/api/export_interns.php?format=csv" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-csv"></i> CSV
            </a>
        </div>
    </div>

    <div class="card" style="padding:20px">
        <div class="d-flex align-center gap-12 mb-16">
            <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="fw-600">DTR by Intern</div>
                <div class="fs-12 text-muted">Select an intern to export DTR</div>
            </div>
        </div>
        <div class="d-flex gap-8" style="flex-wrap:wrap">
            <select id="dtrInternSelect" class="form-control" style="flex:1;min-width:140px">
                <option value="">— Select Intern —</option>
                <?php foreach ($interns as $i): ?>
                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['first_name'].' '.$i['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="exportDtr('pdf')" class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i></button>
            <button onclick="exportDtr('csv')" class="btn btn-secondary btn-sm"><i class="fas fa-file-csv"></i></button>
        </div>
    </div>

    <div class="card" style="padding:20px">
        <div class="d-flex align-center gap-12 mb-16">
            <div class="stat-icon green"><i class="fas fa-file-alt"></i></div>
            <div>
                <div class="fw-600">Requirements by Intern</div>
                <div class="fs-12 text-muted">Select an intern to export requirements</div>
            </div>
        </div>
        <div class="d-flex gap-8" style="flex-wrap:wrap">
            <select id="reqInternSelect" class="form-control" style="flex:1;min-width:140px">
                <option value="">— Select Intern —</option>
                <?php foreach ($interns as $i): ?>
                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['first_name'].' '.$i['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="exportReqs('pdf')" class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i></button>
        </div>
    </div>
</div>

<!-- Department Summary Table -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-building text-orange"></i> Department Summary</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Active Interns</th>
                        <th>Total Rendered Hrs</th>
                        <th>Total Required Hrs</th>
                        <th>Avg Completion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($depts as $d):
                    $avg = $d['total_required'] > 0
                        ? min(100, round(($d['total_rendered'] / $d['total_required']) * 100))
                        : 0;
                ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($d['name']) ?></td>
                    <td><?= $d['active_count'] ?></td>
                    <td><?= number_format($d['total_rendered'], 1) ?></td>
                    <td><?= number_format($d['total_required'], 0) ?></td>
                    <td>
                        <div class="d-flex align-center gap-8">
                            <div class="progress-bar-wrap" style="flex:1">
                                <div class="progress-bar-fill" style="width:<?= $avg ?>%"></div>
                            </div>
                            <span class="fs-12 text-muted"><?= $avg ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- All Interns Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users text-orange"></i> All Interns Overview</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Rendered</th>
                        <th>Required</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($interns as $i):
                    $pct = $i['required_hours'] > 0
                        ? min(100, round(($i['rendered_hours'] / $i['required_hours']) * 100))
                        : 0;
                ?>
                <tr>
                    <td class="fw-600">
                        <a href="/intern_workspace.php?id=<?= $i['id'] ?>" class="text-orange">
                            <?= htmlspecialchars($i['first_name'].' '.$i['last_name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($i['dept_name']) ?></td>
                    <td><span class="badge badge-<?= strtolower($i['status']) ?>"><?= $i['status'] ?></span></td>
                    <td class="fs-12 text-muted"><?= $i['start_date'] ?? '—' ?></td>
                    <td class="fs-12 text-muted"><?= $i['end_date']   ?? '—' ?></td>
                    <td><?= number_format($i['rendered_hours'],1) ?></td>
                    <td><?= number_format($i['required_hours'],0) ?></td>
                    <td style="min-width:100px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="fs-12 text-muted"><?= $pct ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportDtr(fmt) {
    const id = document.getElementById('dtrInternSelect').value;
    if (!id) { showToast('Please select an intern first.', 'warning'); return; }
    window.open(`/api/export_dtr.php?intern_id=${id}&format=${fmt}`, '_blank');
}
function exportReqs(fmt) {
    const id = document.getElementById('reqInternSelect').value;
    if (!id) { showToast('Please select an intern first.', 'warning'); return; }
    window.open(`/api/export_requirements.php?intern_id=${id}&format=${fmt}`, '_blank');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

$db = getDB();

$search       = trim($_GET['search'] ?? '');
$deptFilter   = (int)($_GET['dept'] ?? 0);
$statusFilter = $_GET['status'] ?? 'Active';

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($statusFilter === 'Archived') {
    $where .= " AND i.status = 'Archived'";
} else {
    $where .= " AND i.status = 'Active'";
}

if ($deptFilter) {
    $where   .= " AND i.department_id = ?";
    $params[] = $deptFilter;
    $types   .= 'i';
}

if ($search !== '') {
    $where   .= " AND CONCAT(i.first_name,' ',i.last_name) LIKE ?";
    $params[] = "%{$search}%";
    $types   .= 's';
}

$sql  = "SELECT i.*, d.name AS dept_name FROM interns i
         JOIN departments d ON d.id = i.department_id
         {$where}
         ORDER BY i.last_name, i.first_name";

if ($types) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $interns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $interns = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$depts = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Intern Management';
$breadcrumbs = [['label' => 'Intern Management', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Intern Management</h1>
    <p>View and manage all interns across departments.</p>
</div>

<!-- Toolbar -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name…"
                       value="<?= htmlspecialchars($search) ?>" maxlength="100">
            </div>
            <select name="dept" class="form-control" style="width:auto">
                <option value="">All Departments</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deptFilter==$d['id']?'selected':'' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control" style="width:auto">
                <option value="Active"   <?= $statusFilter==='Active'  ?'selected':'' ?>>Active</option>
                <option value="Archived" <?= $statusFilter==='Archived'?'selected':'' ?>>Archived</option>
            </select>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/interns.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Results -->
<?php if (empty($interns)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No <?= strtolower($statusFilter) ?> interns found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
    </div>
</div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= count($interns) ?> Intern<?= count($interns)!=1?'s':'' ?> found</span>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Intern</th>
                        <th>Department</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Hours</th>
                        <th style="width:80px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($interns as $intern):
                    $pct = $intern['required_hours'] > 0
                        ? min(100, round(($intern['rendered_hours'] / $intern['required_hours']) * 100))
                        : 0;
                    $initials = strtoupper(substr($intern['first_name'],0,1).substr($intern['last_name'],0,1));
                ?>
                <tr style="cursor:pointer" onclick="location.href='/intern_workspace.php?id=<?= $intern['id'] ?>'">
                    <td>
                        <div class="d-flex align-center gap-8">
                            <div class="intern-avatar" style="width:36px;height:36px;font-size:14px;flex-shrink:0">
                                <?php if ($intern['profile_photo']): ?>
                                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                                <?php else: ?><?= $initials ?><?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></div>
                                <div class="fs-12 text-muted"><?= htmlspecialchars($intern['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($intern['dept_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($intern['school'] ?: '—') ?></td>
                    <td><span class="badge badge-<?= strtolower($intern['status']) ?>"><?= $intern['status'] ?></span></td>
                    <td style="min-width:100px">
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="fs-12 text-muted"><?= $pct ?>%</span>
                    </td>
                    <td class="fs-12"><?= number_format($intern['rendered_hours'],1) ?> / <?= number_format($intern['required_hours'],0) ?></td>
                    <td onclick="event.stopPropagation()">
                        <a href="/intern_workspace.php?id=<?= $intern['id'] ?>" class="btn btn-icon btn-sm" title="Open">
                            <i class="fas fa-arrow-right" style="color:var(--orange)"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

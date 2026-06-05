<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
checkSession();

// Only admins can view audit trail
if (!isAdmin()) {
    header('Location: /dashboard.php');
    exit;
}

$db = getDB();

$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to']   ?? '';
$userFilter = trim($_GET['user'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');

$where  = "WHERE 1=1";
$params = [];
$types  = '';

if ($dateFrom) { $where .= " AND DATE(created_at) >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo)   { $where .= " AND DATE(created_at) <= ?"; $params[] = $dateTo;   $types .= 's'; }
if ($userFilter)   { $where .= " AND user_name LIKE ?"; $params[] = "%{$userFilter}%"; $types .= 's'; }
if ($actionFilter) { $where .= " AND action = ?";       $params[] = $actionFilter;     $types .= 's'; }

$sql = "SELECT * FROM audit_trail {$where} ORDER BY created_at DESC LIMIT 500";

try {
    if ($types) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $logs = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $fetchError = false;
} catch (Exception $e) {
    $logs = [];
    $fetchError = true;
}

// Distinct actions for filter dropdown
$actions = $db->query("SELECT DISTINCT action FROM audit_trail ORDER BY action")->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Audit Trail';
$breadcrumbs = [['label' => 'Audit Trail', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Audit Trail</h1>
    <p>All significant system actions are logged here for accountability and traceability.</p>
</div>

<!-- Filters -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" class="toolbar" style="flex-wrap:wrap;gap:10px">
            <div class="d-flex align-center gap-8">
                <label class="form-label" style="margin:0;white-space:nowrap">From</label>
                <input type="date" name="date_from" class="form-control" style="width:150px"
                       value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="d-flex align-center gap-8">
                <label class="form-label" style="margin:0;white-space:nowrap">To</label>
                <input type="date" name="date_to" class="form-control" style="width:150px"
                       value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="search-box" style="max-width:200px">
                <i class="fas fa-user"></i>
                <input type="text" name="user" placeholder="Filter by user…"
                       value="<?= htmlspecialchars($userFilter) ?>">
            </div>
            <select name="action" class="form-control" style="width:auto">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= htmlspecialchars($a['action']) ?>"
                    <?= $actionFilter===$a['action']?'selected':'' ?>>
                    <?= htmlspecialchars($a['action']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/audit.php" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Log Table -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-history text-orange"></i> Activity Log</span>
        <span class="text-muted fs-12"><?= count($logs) ?> record<?= count($logs)!=1?'s':'' ?> (max 500)</span>
    </div>
    <div class="card-body" style="padding:0">
        <?php if ($fetchError): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i>
            <p style="color:var(--danger)">Unable to retrieve audit log. Please try again later.</p>
        </div>
        <?php elseif (empty($logs)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No log entries match the selected filters.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Timestamp (UTC)</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Record ID</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="fs-12 text-muted" style="white-space:nowrap">
                        <?= htmlspecialchars($log['created_at']) ?>
                    </td>
                    <td class="fw-600"><?= htmlspecialchars($log['user_name'] ?? '—') ?></td>
                    <td>
                        <?php
                        $actionColors = [
                            'CREATE'  => 'badge-approved',
                            'UPDATE'  => 'badge-submitted',
                            'ARCHIVE' => 'badge-archived',
                            'RESTORE' => 'badge-approved',
                            'DELETE'  => 'badge-pending',
                            'LOGIN'   => 'badge-submitted',
                            'LOGOUT'  => 'badge-archived',
                            'LOCK'    => 'badge-pending',
                        ];
                        $cls = $actionColors[$log['action']] ?? 'badge-archived';
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($log['action']) ?></span>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($log['module']) ?></td>
                    <td class="text-muted fs-12"><?= $log['record_id'] ?? '—' ?></td>
                    <td style="max-width:320px;font-size:12.5px"><?= htmlspecialchars($log['description'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

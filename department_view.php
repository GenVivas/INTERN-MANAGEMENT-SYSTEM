<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db    = getDB();
$deptId = (int)($_GET['id'] ?? 0);

$deptStmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$deptStmt->bind_param('i', $deptId);
$deptStmt->execute();
$dept = $deptStmt->get_result()->fetch_assoc();
$deptStmt->close();

if (!$dept) { header('Location: /departments.php'); exit; }

// Handle add/edit/archive intern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_intern') {
        $fn  = trim($_POST['first_name']     ?? '');
        $ln  = trim($_POST['last_name']      ?? '');
        $mn  = trim($_POST['middle_name']    ?? '');
        $sch = trim($_POST['school']         ?? '');
        $crs = trim($_POST['course']         ?? '');
        $rh  = (float)($_POST['required_hours'] ?: 486);
        $sd  = trim($_POST['start_date']     ?? '') ?: null;
        $ed  = trim($_POST['end_date']       ?? '') ?: null;

        if (!$fn || !$ln) {
            $_SESSION['add_error'] = 'First name and last name are required.';
            header("Location: /department_view.php?id={$deptId}");
            exit;
        }

        $stmt = $db->prepare(
            "INSERT INTO interns
             (department_id, first_name, last_name, middle_name, school, course, required_hours, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // 9 params: i, s, s, s, s, s, d, s, s
        $stmt->bind_param('issssssds',
            $deptId, $fn, $ln, $mn, $sch, $crs, $rh, $sd, $ed
        );
        $stmt->execute();
        $newId = $db->insert_id;
        $stmt->close();
        logAudit('CREATE', 'Interns', $newId, "Intern {$fn} {$ln} added to dept {$dept['name']}.");
        // Redirect straight to their 201 profile to complete the record
        header("Location: /intern_workspace.php?id={$newId}&tab=201&new=1");
        exit;

    } elseif ($action === 'archive_intern') {
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Archived' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute();
        $stmt->close();
        logAudit('ARCHIVE', 'Interns', $id, "Intern #{$id} archived.");

    } elseif ($action === 'unarchive_intern') {
        $id = (int)($_POST['intern_id'] ?? 0);
        $stmt = $db->prepare("UPDATE interns SET status='Active' WHERE id=? AND department_id=?");
        $stmt->bind_param('ii', $id, $deptId);
        $stmt->execute();
        $stmt->close();
        logAudit('RESTORE', 'Interns', $id, "Intern #{$id} restored to active.");
    }

    header("Location: /department_view.php?id={$deptId}");
    exit;
}

// Fetch interns
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'Active';
$params       = [$deptId];
$types        = 'i';
$where        = "WHERE i.department_id = ?";

if ($statusFilter === 'Archived') {
    $where .= " AND i.status = 'Archived'";
} elseif ($statusFilter === 'All') {
    // no status filter
} else {
    $where .= " AND i.status = 'Active'";
}

if ($search !== '') {
    $where   .= " AND CONCAT(i.first_name,' ',i.last_name) LIKE ?";
    $params[] = "%{$search}%";
    $types   .= 's';
}

$sql   = "SELECT i.*, d.name AS dept_name FROM interns i JOIN departments d ON d.id=i.department_id {$where} ORDER BY i.last_name, i.first_name";
$stmt  = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$interns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle   = htmlspecialchars($dept['name']);
$breadcrumbs = [
    ['label' => 'Departments', 'url' => '/departments.php'],
    ['label' => $dept['name'], 'url' => ''],
];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1><?= htmlspecialchars($dept['name']) ?></h1>
        <p>Manage interns assigned to this department.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addInternModal')">
        <i class="fas fa-user-plus"></i> Add Intern
    </button>
</div>

<!-- Toolbar -->
<div class="card mb-24">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" action="" class="toolbar">
            <input type="hidden" name="id" value="<?= $deptId ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search interns…"
                       value="<?= htmlspecialchars($search) ?>" maxlength="100">
            </div>
            <select name="status" class="form-control" style="width:auto">
                <option value="Active"   <?= $statusFilter==='Active'  ?'selected':'' ?>>Active</option>
                <option value="Archived" <?= $statusFilter==='Archived'?'selected':'' ?>>Inactive / Archived</option>
                <option value="All"      <?= $statusFilter==='All'     ?'selected':'' ?>>All</option>
            </select>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            <a href="/department_view.php?id=<?= $deptId ?>" class="btn btn-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Intern List -->
<?php if (empty($interns)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No interns found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
    </div>
</div></div>
<?php else: ?>
<div class="intern-grid">
    <?php foreach ($interns as $intern):
        $pct      = $intern['required_hours'] > 0
            ? min(100, round(($intern['rendered_hours'] / $intern['required_hours']) * 100))
            : 0;
        $initials = strtoupper(substr($intern['first_name'],0,1) . substr($intern['last_name'],0,1));
        $isActive = $intern['status'] === 'Active';
    ?>
    <div class="intern-card" onclick="location.href='/intern_workspace.php?id=<?= $intern['id'] ?>'">
        <div class="intern-card-header">
            <div class="intern-avatar">
                <?php if ($intern['profile_photo']): ?>
                <img src="/uploads/photos/<?= htmlspecialchars($intern['profile_photo']) ?>" alt="">
                <?php else: ?>
                <?= $initials ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="intern-card-name"><?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></div>
                <div class="intern-card-dept"><?= htmlspecialchars($intern['school'] ?: '—') ?></div>
            </div>
            <span class="badge badge-<?= strtolower($intern['status']) ?>" style="margin-left:auto">
                <?= $intern['status'] ?>
            </span>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="intern-card-meta">
            <span><?= number_format($intern['rendered_hours'],1) ?> / <?= number_format($intern['required_hours'],0) ?> hrs</span>
            <span><?= $pct ?>% complete</span>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px" onclick="event.stopPropagation()">
            <a href="/intern_workspace.php?id=<?= $intern['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center">
                <i class="fas fa-eye"></i> View
            </a>
            <?php if ($isActive): ?>
            <button class="btn btn-secondary btn-sm" title="Archive"
                onclick="archiveIntern(<?= $intern['id'] ?>, '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                <i class="fas fa-archive"></i>
            </button>
            <?php else: ?>
            <button class="btn btn-success btn-sm" title="Restore"
                onclick="unarchiveIntern(<?= $intern['id'] ?>, '<?= htmlspecialchars(addslashes($intern['first_name'].' '.$intern['last_name'])) ?>')">
                <i class="fas fa-undo"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Intern Modal — minimal, redirect to 201 profile for full details -->
<div class="modal-overlay" id="addInternModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-user-plus text-orange"></i> Add New Intern</span>
            <button class="modal-close" onclick="closeModal('addInternModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_intern">
            <div class="modal-body">
                <p class="text-muted mb-16" style="font-size:13px">
                    Fill in the basics to create the record. You'll be taken to the full 201 Profile to complete all details.
                </p>

                <?php if (!empty($_SESSION['add_error'])): ?>
                <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:var(--danger);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;display:flex;gap:8px;align-items:center">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['add_error']) ?>
                </div>
                <?php unset($_SESSION['add_error']); endif; ?>

                <!-- Name row -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" required maxlength="80" placeholder="e.g. Maria">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" required maxlength="80" placeholder="e.g. Santos">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" maxlength="80" placeholder="Optional">
                </div>

                <!-- School row -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">School / University</label>
                        <input type="text" name="school" class="form-control" maxlength="150" placeholder="e.g. University of Caloocan City">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" class="form-control" maxlength="150" placeholder="e.g. BS Information Technology">
                    </div>
                </div>

                <!-- Hours + Dates -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Required Hours</label>
                        <input type="number" name="required_hours" class="form-control" value="486" min="1" step="0.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addInternModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Create &amp; Open Profile
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Archive Confirm Modal -->
<div class="modal-overlay" id="archiveModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Archive Intern</span>
            <button class="modal-close" onclick="closeModal('archiveModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="archiveForm">
            <input type="hidden" name="action" value="archive_intern">
            <input type="hidden" name="intern_id" id="archiveInternId">
            <div class="modal-body">
                <p>Are you sure you want to archive <strong id="archiveInternName"></strong>?</p>
                <p class="text-muted mt-8" style="font-size:13px">The intern's record will be preserved and can be restored later.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('archiveModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-archive"></i> Archive</button>
            </div>
        </form>
    </div>
</div>

<!-- Unarchive Confirm Modal -->
<div class="modal-overlay" id="unarchiveModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Restore Intern</span>
            <button class="modal-close" onclick="closeModal('unarchiveModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="unarchive_intern">
            <input type="hidden" name="intern_id" id="unarchiveInternId">
            <div class="modal-body">
                <p>Restore <strong id="unarchiveInternName"></strong> to Active?</p>
                <p class="text-muted mt-8" style="font-size:13px">The intern will be moved back to the active list.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('unarchiveModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-undo"></i> Restore</button>
            </div>
        </form>
    </div>
</div>

<script>
function archiveIntern(id, name) {
    document.getElementById('archiveInternId').value = id;
    document.getElementById('archiveInternName').textContent = name;
    openModal('archiveModal');
}
function unarchiveIntern(id, name) {
    document.getElementById('unarchiveInternId').value = id;
    document.getElementById('unarchiveInternName').textContent = name;
    openModal('unarchiveModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

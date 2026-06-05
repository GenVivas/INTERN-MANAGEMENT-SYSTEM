<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db = getDB();

// Handle add/edit/delete via POST (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();
            logAudit('CREATE', 'Departments', $newId, "Department '{$name}' created.");
        }
    } elseif ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $stmt = $db->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $stmt->close();
            logAudit('UPDATE', 'Departments', $id, "Department renamed to '{$name}'.");
        }
    }
    header('Location: /departments.php');
    exit;
}

$depts = $db->query(
    "SELECT d.id, d.name,
            COUNT(CASE WHEN i.status='Active' THEN 1 END) AS active_count,
            COUNT(i.id) AS total_count
     FROM departments d
     LEFT JOIN interns i ON i.department_id = d.id
     GROUP BY d.id, d.name
     ORDER BY d.name ASC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Departments';
$breadcrumbs = [['label' => 'Departments', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Departments</h1>
        <p>Manage organizational departments and view intern distribution.</p>
    </div>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary" onclick="openModal('addDeptModal')">
        <i class="fas fa-plus"></i> Add Department
    </button>
    <?php endif; ?>
</div>

<?php if (empty($depts)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-building"></i>
        <p>No departments configured yet.</p>
    </div>
</div></div>
<?php else: ?>
<div class="dept-grid">
    <?php foreach ($depts as $dept): ?>
    <div class="dept-card" style="position:relative">
        <a href="/department_view.php?id=<?= $dept['id'] ?>" style="position:absolute;inset:0;z-index:0"></a>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;position:relative;z-index:1">
            <div class="dept-card-icon"><i class="fas fa-building"></i></div>
            <?php if (isAdmin()): ?>
            <button class="btn btn-icon btn-sm"
                onclick="event.stopPropagation();openEditDept(<?= $dept['id'] ?>, '<?= htmlspecialchars(addslashes($dept['name'])) ?>')"
                title="Edit">
                <i class="fas fa-pen"></i>
            </button>
            <?php endif; ?>
        </div>
        <div class="dept-card-name" style="position:relative;z-index:1"><?= htmlspecialchars($dept['name']) ?></div>
        <div class="dept-card-count" style="position:relative;z-index:1"><?= $dept['active_count'] ?></div>
        <div class="dept-card-label" style="position:relative;z-index:1">
            Active Intern<?= $dept['active_count'] != 1 ? 's' : '' ?>
            <span class="text-muted"> · <?= $dept['total_count'] ?> total</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add Department Modal -->
<div class="modal-overlay" id="addDeptModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Department</span>
            <button class="modal-close" onclick="closeModal('addDeptModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Finance" required maxlength="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal-overlay" id="editDeptModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Department</span>
            <button class="modal-close" onclick="closeModal('editDeptModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editDeptId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" id="editDeptName" class="form-control" required maxlength="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditDept(id, name) {
    document.getElementById('editDeptId').value   = id;
    document.getElementById('editDeptName').value = name;
    openModal('editDeptModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

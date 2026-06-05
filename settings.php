<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();
requireRole('admin');

$db = getDB();
$success = '';
$error   = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add user
    if ($action === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'hr_staff';

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $name, $email, $hash, $role);
            if ($stmt->execute()) {
                $newId = $db->insert_id;
                logAudit('CREATE', 'Users', $newId, "User '{$name}' ({$email}) created with role '{$role}'.");
                $success = "User '{$name}' created successfully.";
            } else {
                $error = 'Email already exists.';
            }
            $stmt->close();
        }
    }

    // Unlock user
    if ($action === 'unlock_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET is_locked=0, fail_count=0 WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        logAudit('UPDATE', 'Users', $id, "User #{$id} account unlocked.");
        $success = 'Account unlocked.';
    }

    // Change own password
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param('i', currentUserId());
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param('si', $hash, currentUserId());
            $stmt->execute();
            $stmt->close();
            logAudit('UPDATE', 'Users', currentUserId(), 'Password changed.');
            $success = 'Password updated successfully.';
        }
    }
}

$users = $db->query("SELECT id, name, email, role, is_locked, fail_count, created_at FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle   = 'Settings';
$breadcrumbs = [['label' => 'Settings', 'url' => '']];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Settings</h1>
    <p>Manage system users and account settings.</p>
</div>

<?php if ($success): ?>
<div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:var(--success);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:var(--danger);border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

<!-- Users List -->
<div class="card" style="grid-column:1/-1">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users text-orange"></i> System Users</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('addUserModal')">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($u['name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin'?'badge-approved':'badge-submitted' ?>">
                            <?= ucfirst(str_replace('_',' ',$u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['is_locked']): ?>
                        <span class="badge badge-pending"><i class="fas fa-lock"></i> Locked</span>
                        <?php else: ?>
                        <span class="badge badge-active"><i class="fas fa-check"></i> Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="fs-12 text-muted"><?= htmlspecialchars($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['is_locked']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action"  value="unlock_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-icon btn-sm" title="Unlock account">
                                <i class="fas fa-unlock" style="color:var(--success)"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Change Password -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-key text-orange"></i> Change Password</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
        </form>
    </div>
</div>

</div><!-- /grid -->

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add User</span>
            <button class="modal-close" onclick="closeModal('addUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                    <span class="form-error" style="color:var(--text-muted)">Minimum 8 characters</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="hr_staff">HR Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// Requirements Tab — all POST handling is done in intern_workspace.php

// Fetch active
$stmt = $db->prepare("SELECT * FROM requirement_items WHERE intern_id=? AND is_archived=0 ORDER BY created_at ASC");
$stmt->bind_param('i', $internId); $stmt->execute();
$requirements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

// Fetch archived
$stmt = $db->prepare("SELECT * FROM requirement_items WHERE intern_id=? AND is_archived=1 ORDER BY created_at ASC");
$stmt->bind_param('i', $internId); $stmt->execute();
$archivedReqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$total     = count($requirements);
$approved  = count(array_filter($requirements, fn($r) => $r['status'] === 'Approved'));
$pending   = count(array_filter($requirements, fn($r) => $r['status'] === 'Pending'));
$submitted = count(array_filter($requirements, fn($r) => $r['status'] === 'Submitted'));

// Helper: is image?
function isImage(string $path): bool {
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg','jpeg','png']);
}
?>

<!-- Toolbar -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:10px">
            <div class="d-flex align-center gap-8">
                <span class="badge badge-approved"><i class="fas fa-check"></i> <?= $approved ?> Approved</span>
                <span class="badge badge-submitted"><i class="fas fa-paper-plane"></i> <?= $submitted ?> Submitted</span>
                <span class="badge badge-pending"><i class="fas fa-clock"></i> <?= $pending ?> Pending</span>
            </div>
            <div class="d-flex gap-8">
                <button class="btn btn-primary btn-sm" onclick="openModal('addReqModal')">
                    <i class="fas fa-plus"></i> Add Requirement
                </button>
                <a href="/api/export_requirements.php?intern_id=<?= $internId ?>" class="btn btn-secondary btn-sm" target="_blank">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <?php if (!empty($archivedReqs)): ?>
                <button class="btn btn-secondary btn-sm" onclick="toggleArchived()">
                    <i class="fas fa-archive"></i> Archived (<?= count($archivedReqs) ?>)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Requirements Table -->
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-file-alt text-orange"></i> Requirements Tracking</span>
        <span class="text-muted fs-12"><?= $total ?> requirement<?= $total!=1?'s':'' ?></span>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (empty($requirements)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>No active requirements. Click "Add Requirement" to get started.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="ims-table">
                <thead>
                    <tr>
                        <th style="width:28%">Requirement</th>
                        <th style="width:130px">Status</th>
                        <th style="width:120px">Date Submitted</th>
                        <th>Remarks</th>
                        <th style="width:140px">File</th>
                        <th style="width:90px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requirements as $req): ?>
                <tr id="req-row-<?= $req['id'] ?>">
                    <td class="fw-600"><?= htmlspecialchars($req['name']) ?></td>
                    <td>
                        <select class="form-control" style="padding:4px 8px;font-size:12.5px"
                                onchange="updateReqStatus(<?= $req['id'] ?>, this.value)">
                            <?php foreach (['Pending','Submitted','Approved'] as $s): ?>
                            <option value="<?= $s ?>" <?= $req['status']===$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="text-muted fs-12">
                        <?= $req['submission_date'] ? htmlspecialchars($req['submission_date']) : '—' ?>
                    </td>
                    <td>
                        <input type="text" class="form-control" style="font-size:12.5px"
                               value="<?= htmlspecialchars($req['remarks'] ?? '') ?>"
                               maxlength="500" placeholder="Add remarks…"
                               onblur="updateReqRemarks(<?= $req['id'] ?>, this.value)">
                    </td>
                    <td>
                        <?php if ($req['file_path']): ?>
                        <div class="d-flex align-center gap-6">
                            <?php if (isImage($req['file_path'])): ?>
                            <!-- Thumbnail preview -->
                            <img src="/uploads/requirements/<?= htmlspecialchars($req['file_path']) ?>"
                                 alt="preview"
                                 style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--gray-border);cursor:pointer"
                                 onclick="viewFile('<?= htmlspecialchars($req['file_path']) ?>', '<?= htmlspecialchars(addslashes($req['name'])) ?>', 'image')"
                                 title="Click to view">
                            <?php else: ?>
                            <!-- PDF/DOCX icon -->
                            <div style="width:36px;height:36px;background:var(--orange-light);border-radius:6px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:1px solid var(--gray-border)"
                                 onclick="viewFile('<?= htmlspecialchars($req['file_path']) ?>', '<?= htmlspecialchars(addslashes($req['name'])) ?>', 'pdf')"
                                 title="Click to view">
                                <i class="fas fa-file-pdf" style="color:var(--orange);font-size:16px"></i>
                            </div>
                            <?php endif; ?>
                            <span class="fs-12 text-muted" style="max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                  title="<?= htmlspecialchars($req['file_name'] ?? $req['file_path']) ?>">
                                <?= htmlspecialchars($req['file_name'] ?? $req['file_path']) ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <span class="text-muted fs-12">No file</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-6">
                            <!-- Upload -->
                            <label class="btn btn-icon btn-sm" title="Upload file" style="cursor:pointer">
                                <i class="fas fa-upload"></i>
                                <input type="file" style="display:none"
                                       accept=".pdf,.jpg,.jpeg,.png,.docx"
                                       onchange="uploadReqFile(<?= $req['id'] ?>, this)">
                            </label>
                            <!-- View -->
                            <?php if ($req['file_path']): ?>
                            <button class="btn btn-icon btn-sm" title="View file"
                                    onclick="viewFile('<?= htmlspecialchars($req['file_path']) ?>', '<?= htmlspecialchars(addslashes($req['name'])) ?>', '<?= isImage($req['file_path'])?'image':'pdf' ?>')">
                                <i class="fas fa-eye" style="color:var(--info)"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-icon btn-sm" disabled title="No file" style="opacity:.35">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                            <!-- Archive -->
                            <button class="btn btn-icon btn-sm" title="Archive"
                                    onclick="archiveReq(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['name'])) ?>')">
                                <i class="fas fa-archive" style="color:var(--gray-mid)"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Archived Requirements -->
<div id="archivedReqsSection" style="display:none">
    <div class="card">
        <div class="card-header">
            <span class="card-title text-muted"><i class="fas fa-archive"></i> Archived Requirements</span>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($archivedReqs)): ?>
            <div class="empty-state"><i class="fas fa-archive"></i><p>No archived requirements.</p></div>
            <?php else: ?>
            <div class="table-wrapper">
                <table class="ims-table">
                    <thead>
                        <tr><th>Requirement</th><th>Status</th><th>Date Submitted</th><th>File</th><th style="width:80px">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($archivedReqs as $req): ?>
                    <tr id="arch-req-row-<?= $req['id'] ?>" style="opacity:.65">
                        <td><?= htmlspecialchars($req['name']) ?></td>
                        <td><span class="badge badge-archived"><?= $req['status'] ?></span></td>
                        <td class="fs-12 text-muted"><?= $req['submission_date'] ?? '—' ?></td>
                        <td>
                            <?php if ($req['file_path']): ?>
                            <button class="btn btn-icon btn-sm"
                                    onclick="viewFile('<?= htmlspecialchars($req['file_path']) ?>', '<?= htmlspecialchars(addslashes($req['name'])) ?>', '<?= isImage($req['file_path'])?'image':'pdf' ?>')">
                                <i class="fas fa-eye" style="color:var(--info)"></i>
                            </button>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-icon btn-sm" title="Restore" onclick="restoreReq(<?= $req['id'] ?>)">
                                <i class="fas fa-undo" style="color:var(--success)"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== File Viewer Modal ===== -->
<div class="modal-overlay" id="fileViewerModal">
    <div class="modal modal-lg" style="max-width:820px">
        <div class="modal-header">
            <span class="modal-title" id="fileViewerTitle">View File</span>
            <div class="d-flex gap-8">
                <a id="fileViewerDownload" href="#" target="_blank" class="btn btn-secondary btn-sm">
                    <i class="fas fa-download"></i> Download
                </a>
                <button class="modal-close" onclick="closeModal('fileViewerModal')"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body" style="padding:16px;min-height:400px;display:flex;align-items:center;justify-content:center;background:#f8f8f8;border-radius:0 0 14px 14px">
            <div id="fileViewerContent" style="width:100%;text-align:center"></div>
        </div>
    </div>
</div>

<!-- Add Requirement Modal -->
<div class="modal-overlay" id="addReqModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add Requirement</span>
            <button class="modal-close" onclick="closeModal('addReqModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="addReqError" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:var(--danger);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px"></div>
            <div class="form-group">
                <label class="form-label">Requirement <span class="required">*</span></label>
                <select id="newReqName" class="form-control">
                    <option value="">— Select Requirement —</option>
                    <option value="Endorsement Letter">Endorsement Letter</option>
                    <option value="Letter of Intent">Letter of Intent</option>
                    <option value="MOA">MOA</option>
                    <option value="School ID">School ID</option>
                    <option value="Proof of Registration">Proof of Registration</option>
                    <option value="School Schedule">School Schedule</option>
                    <option value="Parent Consent">Parent Consent</option>
                    <option value="Barangay Clearance">Barangay Clearance</option>
                    <option value="Other">Other (type below)</option>
                </select>
            </div>
            <div class="form-group" id="customReqGroup" style="display:none">
                <label class="form-label">Custom Requirement Name <span class="required">*</span></label>
                <input type="text" id="customReqName" class="form-control" maxlength="200"
                       placeholder="Enter requirement name">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addReqModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitAddReq()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
    </div>
</div>

<!-- Archive Confirm Modal -->
<div class="modal-overlay" id="archiveReqModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Archive Requirement</span>
            <button class="modal-close" onclick="closeModal('archiveReqModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p>Archive <strong id="archiveReqName"></strong>?</p>
            <p class="text-muted mt-8" style="font-size:13px">It will be hidden from the active list but preserved in storage.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('archiveReqModal')">Cancel</button>
            <button type="button" class="btn btn-danger" id="archiveReqConfirmBtn">
                <i class="fas fa-archive"></i> Archive
            </button>
        </div>
    </div>
</div>

<script>
const REQ_INTERN_ID = <?= $internId ?>;

function postReq(data) {
    const fd = new FormData();
    for (const [k,v] of Object.entries(data)) fd.append(k, v);
    return fetch(`/intern_workspace.php?id=${REQ_INTERN_ID}&tab=reqs`, { method:'POST', body:fd })
           .then(r => r.json());
}

function submitAddReq() {
    const select  = document.getElementById('newReqName');
    const custom  = document.getElementById('customReqName');
    const errBox  = document.getElementById('addReqError');
    const isOther = select.value === 'Other';
    const name    = isOther ? custom.value.trim() : select.value.trim();

    if (!name) {
        errBox.textContent = isOther ? 'Please enter a custom requirement name.' : 'Please select a requirement.';
        errBox.style.display = 'block';
        return;
    }
    errBox.style.display = 'none';
    postReq({ action:'add_requirement', req_name:name }).then(res => {
        if (res.success) {
            closeModal('addReqModal');
            select.value = '';
            if (custom) custom.value = '';
            document.getElementById('customReqGroup').style.display = 'none';
            showToast('Requirement added.','success');
            setTimeout(()=>location.reload(),600);
        } else {
            errBox.textContent = res.error;
            errBox.style.display = 'block';
        }
    });
}

// Show/hide custom input
document.getElementById('newReqName')?.addEventListener('change', function() {
    const grp = document.getElementById('customReqGroup');
    grp.style.display = this.value === 'Other' ? 'block' : 'none';
    if (this.value !== 'Other') document.getElementById('customReqName').value = '';
});

function updateReqStatus(id, status) {
    postReq({ action:'update_status', req_id:id, status }).then(res => {
        showToast(res.success ? 'Status updated.' : res.error, res.success ? 'success' : 'error');
    });
}

function updateReqRemarks(id, remarks) {
    postReq({ action:'update_remarks', req_id:id, remarks }).then(res => {
        if (res.success) showToast('Remarks saved.','success');
    });
}

function uploadReqFile(id, input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('action',   'upload_file');
    fd.append('req_id',   id);
    fd.append('req_file', input.files[0]);
    showToast('Uploading…','info');
    fetch(`/intern_workspace.php?id=${REQ_INTERN_ID}&tab=reqs`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { showToast('File uploaded.','success'); setTimeout(()=>location.reload(),700); }
            else showToast(res.error,'error');
        });
}

// ---- File Viewer ----
function viewFile(filePath, reqName, type) {
    const url     = '/uploads/requirements/' + filePath;
    const title   = document.getElementById('fileViewerTitle');
    const content = document.getElementById('fileViewerContent');
    const dl      = document.getElementById('fileViewerDownload');

    title.textContent = reqName;
    dl.href = url;

    if (type === 'image') {
        content.innerHTML = `<img src="${url}" alt="${reqName}"
            style="max-width:100%;max-height:65vh;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.15)">`;
    } else {
        // PDF or DOCX — embed PDF, link for DOCX
        const ext = filePath.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
            content.innerHTML = `<iframe src="${url}" style="width:100%;height:65vh;border:none;border-radius:8px"></iframe>`;
        } else {
            content.innerHTML = `
                <div style="padding:40px;text-align:center">
                    <i class="fas fa-file-word" style="font-size:56px;color:#2563EB;margin-bottom:16px;display:block"></i>
                    <p style="font-size:14px;color:#555;margin-bottom:16px">${reqName}</p>
                    <a href="${url}" download class="btn btn-primary">
                        <i class="fas fa-download"></i> Download File
                    </a>
                </div>`;
        }
    }
    openModal('fileViewerModal');
}

function archiveReq(id, name) {
    document.getElementById('archiveReqName').textContent = name;
    document.getElementById('archiveReqConfirmBtn').onclick = () => {
        postReq({ action:'archive_requirement', req_id:id }).then(res => {
            if (res.success) {
                closeModal('archiveReqModal');
                document.getElementById('req-row-'+id)?.remove();
                showToast('Requirement archived.','success');
                setTimeout(()=>location.reload(),800);
            } else showToast('Failed to archive.','error');
        });
    };
    openModal('archiveReqModal');
}

function restoreReq(id) {
    postReq({ action:'restore_requirement', req_id:id }).then(res => {
        if (res.success) { showToast('Requirement restored.','success'); setTimeout(()=>location.reload(),600); }
        else showToast('Failed to restore.','error');
    });
}

function toggleArchived() {
    const sec = document.getElementById('archivedReqsSection');
    sec.style.display = sec.style.display === 'none' ? 'block' : 'none';
}
</script>

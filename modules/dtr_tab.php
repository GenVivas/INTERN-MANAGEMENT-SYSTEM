<?php
// DTR Tab — all POST handling is done in intern_workspace.php

// Fetch filter params
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$where    = "WHERE intern_id = ? AND is_archived = 0";
$params   = [$internId];
$types    = 'i';

if ($dateFrom) { $where .= " AND entry_date >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo)   { $where .= " AND entry_date <= ?"; $params[] = $dateTo;   $types .= 's'; }

$stmt = $db->prepare("SELECT * FROM dtr_entries {$where} ORDER BY entry_date ASC, id ASC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Re-fetch intern for updated hours
$stmt = $db->prepare("SELECT rendered_hours, required_hours FROM interns WHERE id=?");
$stmt->bind_param('i', $internId);
$stmt->execute();
$hrs = $stmt->get_result()->fetch_assoc();
$stmt->close();
$remaining = max(0, $hrs['required_hours'] - $hrs['rendered_hours']);
?>

<!-- DTR Toolbar -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px">
        <div class="d-flex align-center justify-between" style="flex-wrap:wrap;gap:10px">
            <form method="GET" class="d-flex align-center gap-8" style="flex-wrap:wrap">
                <input type="hidden" name="id"  value="<?= $internId ?>">
                <input type="hidden" name="tab" value="dtr">
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
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <a href="?id=<?= $internId ?>&tab=dtr" class="btn btn-secondary btn-sm">Reset</a>
            </form>
            <div class="d-flex gap-8">
                <button class="btn btn-primary btn-sm" onclick="openModal('addDtrModal')">
                    <i class="fas fa-plus"></i> Add Entry
                </button>
                <a href="/api/export_dtr.php?intern_id=<?= $internId ?>&format=pdf<?= $dateFrom?"&date_from={$dateFrom}":'' ?><?= $dateTo?"&date_to={$dateTo}":'' ?>"
                   class="btn btn-secondary btn-sm" target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="/api/export_dtr.php?intern_id=<?= $internId ?>&format=csv<?= $dateFrom?"&date_from={$dateFrom}":'' ?><?= $dateTo?"&date_to={$dateTo}":'' ?>"
                   class="btn btn-secondary btn-sm">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <?php if ($hrs['rendered_hours'] >= $hrs['required_hours']): ?>
                <a href="/api/export_coc.php?intern_id=<?= $internId ?>"
                   class="btn btn-success btn-sm" target="_blank">
                    <i class="fas fa-certificate"></i> COC
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hours Summary -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($hrs['rendered_hours'],2) ?></div>
            <div class="stat-label">Rendered Hours</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($remaining,2) ?></div>
            <div class="stat-label">Remaining Hours</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-list-ol"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= count($entries) ?></div>
            <div class="stat-label">Entries <?= ($dateFrom||$dateTo)?'(filtered)':'' ?></div>
        </div>
    </div>
</div>

<!-- DTR Table -->
<div class="card">
    <div class="card-body" style="padding:0">
        <?php if (empty($entries)): ?>
        <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No DTR entries found.</p></div>
        <?php else: ?>
        <div class="table-wrapper">
            <table class="ims-table" id="dtrTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Rendered Hrs</th>
                        <th>Overtime</th>
                        <th>Remarks</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $i => $e): ?>
                <tr id="dtr-row-<?= $e['id'] ?>">
                    <td class="text-muted fs-12"><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($e['entry_date']) ?></td>
                    <td class="editable">
                        <input type="time" value="<?= htmlspecialchars($e['time_in'] ?? '') ?>"
                               data-id="<?= $e['id'] ?>" data-field="time_in"
                               <?php if (in_array($e['remarks'] ?? '', ['Absent','Holiday','No Office','Excused'])): ?>
                               disabled style="opacity:.35"
                               <?php endif; ?>
                               onchange="saveDtrEdit(<?= $e['id'] ?>, this)">
                    </td>
                    <td class="editable">
                        <input type="time" value="<?= htmlspecialchars($e['time_out'] ?? '') ?>"
                               data-id="<?= $e['id'] ?>" data-field="time_out"
                               <?php if (in_array($e['remarks'] ?? '', ['Absent','Holiday','No Office','Excused'])): ?>
                               disabled style="opacity:.35"
                               <?php endif; ?>
                               onchange="saveDtrEdit(<?= $e['id'] ?>, this)">
                    </td>
                    <td id="rh-<?= $e['id'] ?>">
                        <?= in_array($e['remarks'] ?? '', ['Absent','Holiday','No Office','Excused']) ? '0.00' : number_format($e['rendered_hours'],2) ?>
                    </td>
                    <td id="ot-<?= $e['id'] ?>" class="<?= (!in_array($e['remarks'] ?? '', ['Absent','Holiday','No Office','Excused']) && $e['overtime']>0) ? 'text-orange' : '' ?>">
                        <?= in_array($e['remarks'] ?? '', ['Absent','Holiday','No Office','Excused']) ? '0.00' : number_format($e['overtime'],2) ?>
                    </td>
                    <td>
                        <?php
                        $remarkOptions = ['', 'Half Day', 'Excused', 'Absent', 'Holiday', 'No Office'];
                        $currentRemark = $e['remarks'] ?? '';
                        ?>
                        <select class="form-control" style="padding:4px 8px;font-size:12px;min-width:110px"
                                onchange="saveDtrRemark(<?= $e['id'] ?>, this.value)">
                            <?php foreach ($remarkOptions as $opt): ?>
                            <option value="<?= $opt ?>" <?= $currentRemark === $opt ? 'selected' : '' ?>>
                                <?= $opt === '' ? '— None —' : $opt ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-icon btn-sm" title="Delete"
                                onclick="deleteDtrEntry(<?= $e['id'] ?>)">
                            <i class="fas fa-trash" style="color:var(--danger)"></i>
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

<!-- Add DTR Modal -->
<div class="modal-overlay" id="addDtrModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add DTR Entry</span>
            <button class="modal-close" onclick="closeModal('addDtrModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="dtrAddError" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:var(--danger);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px"></div>

            <div class="form-group">
                <label class="form-label">Date <span class="required">*</span></label>
                <input type="date" id="dtrDate" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Remarks</label>
                <select id="dtrRemarks" class="form-control" onchange="toggleDtrTimeFields()">
                    <option value="">— None —</option>
                    <option value="Half Day">Half Day</option>
                    <option value="Excused">Excused</option>
                    <option value="Absent">Absent</option>
                    <option value="Holiday">Holiday</option>
                    <option value="No Office">No Office</option>
                </select>
            </div>

            <div id="dtrTimeFields">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Time In <span class="required">*</span></label>
                        <input type="time" id="dtrTimeIn" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time Out <span class="required">*</span></label>
                        <input type="time" id="dtrTimeOut" class="form-control">
                    </div>
                </div>
            </div>

            <div id="dtrNoTimeMsg" style="display:none;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);color:#3B82F6;border-radius:8px;padding:10px 14px;font-size:13px;display:none">
                <i class="fas fa-info-circle"></i> No time entry needed for this remark type.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addDtrModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitAddDtr()">
                <i class="fas fa-plus"></i> Add Entry
            </button>
        </div>
    </div>
</div>

<script>
const DTR_INTERN_ID = <?= $internId ?>;
const NO_TIME_REMARKS = ['Absent','Holiday','No Office','Excused'];

function toggleDtrTimeFields() {
    const remarks  = document.getElementById('dtrRemarks').value;
    const timeWrap = document.getElementById('dtrTimeFields');
    const infoMsg  = document.getElementById('dtrNoTimeMsg');
    const isNoTime = NO_TIME_REMARKS.includes(remarks);
    timeWrap.style.display = isNoTime ? 'none' : 'block';
    infoMsg.style.display  = isNoTime ? 'flex' : 'none';
}

function submitAddDtr() {
    const date     = document.getElementById('dtrDate').value;
    const remarks  = document.getElementById('dtrRemarks').value;
    const isNoTime = NO_TIME_REMARKS.includes(remarks);
    const timeIn   = isNoTime ? '' : document.getElementById('dtrTimeIn').value;
    const timeOut  = isNoTime ? '' : document.getElementById('dtrTimeOut').value;

    document.getElementById('dtrAddError').style.display = 'none';

    if (!date) { showDtrError('Date is required.'); return; }
    if (!isNoTime) {
        if (!timeIn || !timeOut) { showDtrError('Time In and Time Out are required.'); return; }
        if (timeOut <= timeIn)   { showDtrError('Time Out must be later than Time In.'); return; }
    }

    const fd = new FormData();
    fd.append('action',     'add_dtr');
    fd.append('entry_date', date);
    fd.append('time_in',    timeIn);
    fd.append('time_out',   timeOut);
    fd.append('remarks',    remarks);

    fetch(`/intern_workspace.php?id=${DTR_INTERN_ID}&tab=dtr`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeModal('addDtrModal');
                showToast('DTR entry added.', 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                showDtrError(res.error);
            }
        });
}

function showDtrError(msg) {
    const box = document.getElementById('dtrAddError');
    box.textContent  = msg;
    box.style.display = 'flex';
    box.style.alignItems = 'center';
    box.style.gap = '8px';
}

function saveDtrEdit(id, input) {
    const row     = document.getElementById('dtr-row-' + id);
    const timeIn  = row.querySelector('[data-field="time_in"]').value;
    const timeOut = row.querySelector('[data-field="time_out"]').value;

    if (timeOut <= timeIn) { showToast('Time Out must be later than Time In.', 'error'); return; }

    const fd = new FormData();
    fd.append('action',   'edit_dtr');
    fd.append('entry_id', id);
    fd.append('time_in',  timeIn);
    fd.append('time_out', timeOut);

    fetch(`/intern_workspace.php?id=${DTR_INTERN_ID}&tab=dtr`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { showToast('Entry updated.', 'success'); setTimeout(() => location.reload(), 800); }
            else showToast(res.error, 'error');
        });
}

function deleteDtrEntry(id) {
    if (!confirm('Delete this DTR entry? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action',   'delete_dtr');
    fd.append('entry_id', id);

    fetch(`/intern_workspace.php?id=${DTR_INTERN_ID}&tab=dtr`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { document.getElementById('dtr-row-' + id).remove(); showToast('Entry deleted.', 'success'); setTimeout(() => location.reload(), 600); }
            else showToast('Failed to delete entry.', 'error');
        });
}

function saveDtrRemark(id, remarks) {
    const fd = new FormData();
    fd.append('action',   'edit_dtr_remark');
    fd.append('entry_id', id);
    fd.append('remarks',  remarks);

    fetch(`/intern_workspace.php?id=${DTR_INTERN_ID}&tab=dtr`, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('Remarks saved.', 'success');
                const row    = document.getElementById('dtr-row-' + id);
                const tIn    = row.querySelector('[data-field="time_in"]');
                const tOut   = row.querySelector('[data-field="time_out"]');
                const rhCell = document.getElementById('rh-' + id);
                const otCell = document.getElementById('ot-' + id);
                const clear  = ['Absent','Holiday','No Office','Excused'].includes(remarks);
                if (clear) {
                    if (tIn)  { tIn.value  = ''; tIn.disabled  = true; tIn.style.opacity  = '.35'; }
                    if (tOut) { tOut.value = ''; tOut.disabled = true; tOut.style.opacity = '.35'; }
                    if (rhCell) rhCell.textContent = '0.00';
                    if (otCell) { otCell.textContent = '0.00'; otCell.className = ''; }
                } else {
                    if (tIn)  { tIn.disabled  = false; tIn.style.opacity  = '1'; }
                    if (tOut) { tOut.disabled = false; tOut.style.opacity = '1'; }
                }
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Failed to save remarks.', 'error');
            }
        });
}
</script>

/* ============================================================
   TDT Powersteel IMS — Main JavaScript
   ============================================================ */

// ---------- Modal helpers ----------
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// ---------- Sidebar toggle (mobile) ----------
const sidebarToggle  = document.getElementById('sidebarToggle');
const sidebarClose   = document.getElementById('sidebarClose');
const sidebar        = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar?.classList.add('open');
    sidebarOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar?.classList.remove('open');
    sidebarOverlay?.classList.remove('open');
    document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

// ---------- Toast notifications ----------
function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
        success: 'fa-check-circle',
        error:   'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info:    'fa-info-circle',
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}" style="flex-shrink:0"></i>
                       <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'opacity .3s, transform .3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ---------- Auto-dismiss flash messages ----------
document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity .4s';
        setTimeout(() => el.remove(), 400);
    }, 4000);
});

// ---------- Confirm before form submit (data-confirm) ----------
document.addEventListener('submit', function (e) {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
});

// ---------- Active nav highlight (fallback) ----------
(function () {
    const path = window.location.pathname.split('/').pop().replace('.php', '');
    document.querySelectorAll('.nav-item').forEach(item => {
        const href = item.getAttribute('href') || '';
        if (href.includes(path) && path !== '') {
            item.classList.add('active');
        }
    });
})();

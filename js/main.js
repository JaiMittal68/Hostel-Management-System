// ============================================================
// HOSTEL MANAGEMENT SYSTEM — MAIN JS
// ============================================================

// ---- Modal Helpers ----
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => {
            m.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});

// ---- Sidebar Toggle ----
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// ---- Notification Dropdown ----
function toggleNotifications() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('show');
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function (e) {
    const wrapper = document.querySelector('.notif-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        const dd = document.getElementById('notifDropdown');
        if (dd) dd.classList.remove('show');
    }
});

// ---- Auto-dismiss alerts after 5 seconds ----
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });
});

// ---- Active nav highlighting ----
(function () {
    const path = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(function (item) {
        const href = item.getAttribute('href');
        if (href && href.includes(path) && path !== '') {
            item.classList.add('active');
        }
    });
})();

// ---- Confirm delete buttons ----
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});

// ---- Form validation helper ----
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    let valid = true;
    form.querySelectorAll('[required]').forEach(function (field) {
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger)';
            valid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    return valid;
}

// ---- Number formatting ----
function formatINR(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

// ---- Search with debounce ----
function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ---- Print page ----
function printPage() {
    window.print();
}

// ---- Tooltip on truncated cells ----
document.querySelectorAll('td[title]').forEach(function (td) {
    td.style.cursor = 'help';
});

// ---- Table row click to open detail (optional, enhance per page) ----
// Used if rows have data-href attribute
document.querySelectorAll('tr[data-href]').forEach(function (row) {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function () {
        window.location = row.dataset.href;
    });
});

console.log('%c🏨 Hostel Management System', 'font-size:16px;font-weight:bold;color:#4f46e5');
console.log('%cReady.', 'color:#10b981');

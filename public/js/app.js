const root = document.documentElement;

document.addEventListener('click', e => {
    if (e.target.closest('[data-theme-toggle]')) {
        const t = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', t);
        localStorage.setItem('theme', t);
    }
    if (e.target.closest('[data-sidebar-toggle]')) {
        if (matchMedia('(min-width: 992px)').matches)
            localStorage.setItem('sidebar', root.classList.toggle('sidebar-collapsed') ? 'collapsed' : 'open');
        else document.body.classList.toggle('sidebar-open');
    }
    if (e.target.closest('.sidebar-backdrop')) document.body.classList.remove('sidebar-open');
});

window.toast = (msg, type = 'success') => {
    const icons = { success: 'fa-circle-check', danger: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex align-items-center"><div class="toast-body"><i class="fa-solid ${icons[type] || icons.info} me-2"></i><span></span></div><button class="btn-close me-2 ms-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    el.querySelector('span').textContent = msg;
    document.getElementById('toasts').append(el);
    el.addEventListener('hidden.bs.toast', () => el.remove());
    new bootstrap.Toast(el, { delay: 4000 }).show();
};

document.addEventListener('submit', e => {
    const msg = e.target.dataset?.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
});
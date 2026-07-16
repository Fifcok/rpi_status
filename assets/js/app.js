/**
 * Wspólna logika frontendowa: nawigacja mobilna, pobieranie CSRF, powiadomienia,
 * oraz odświeżanie kafelków dashboardu co 5 sekund (bez przeładowania strony).
 */

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const REFRESH_INTERVAL_MS = 5000;

// Prefiks URL aplikacji (np. "/status"), gdy panel jest zainstalowany w podkatalogu
// domeny zamiast w jej katalogu głównym. Wstrzykiwany przez includes/header.php.
const BASE_PATH = document.querySelector('meta[name="base-path"]')?.content || '';

/** Dokleja BASE_PATH do ścieżek zaczynających się od "/" (zostawia URL-e absolutne bez zmian). */
function withBasePath(url) {
    if (/^https?:\/\//i.test(url) || !url.startsWith('/')) {
        return url;
    }
    return BASE_PATH + url;
}

/** Wrapper na fetch z automatycznym dołączaniem tokenu CSRF dla POST i prefiksu BASE_PATH. */
async function apiFetch(url, options = {}) {
    const opts = { ...options, headers: { ...(options.headers || {}) } };
    if ((opts.method || 'GET').toUpperCase() === 'POST') {
        opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
    }
    const response = await fetch(withBasePath(url), opts);
    if (!response.ok && response.status === 401) {
        window.location.href = BASE_PATH + '/login.php';
        throw new Error('Sesja wygasła.');
    }
    return response.json();
}

function apiPost(url, params = {}) {
    const body = new URLSearchParams({ csrf_token: CSRF_TOKEN, ...params });
    return apiFetch(url, { method: 'POST', body });
}

function showToast(message, variant = 'info') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-bg-${variant} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/** Podmienia treść elementu z krótkim podświetleniem (wizualny feedback odświeżenia). */
function updateField(selector, value) {
    document.querySelectorAll(`[data-field="${selector}"]`).forEach((el) => {
        if (el.textContent.trim() !== String(value)) {
            el.textContent = value;
            el.classList.remove('fade-flash');
            void el.offsetWidth;
            el.classList.add('fade-flash');
        }
    });
}

/* ---------- Sidebar mobilna ---------- */
(function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !sidebar || !backdrop) return;

    const close = () => { sidebar.classList.remove('show'); backdrop.classList.remove('show'); };
    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', close);
})();

/* ---------- Kafelki dashboardu ---------- */
function refreshDashboardTiles() {
    if (!document.getElementById('dashboardTiles')) return;

    apiFetch('/api/dashboard_data.php').then((data) => {
        updateField('cpu_percent', data.cpu_percent ?? '—');
        updateField('cpu_cores', data.cpu_cores ?? '—');
        updateField('cpu_temp', data.cpu_temp !== null ? `${data.cpu_temp} °C` : '—');
        updateField('cpu_freq', data.cpu_freq !== null ? `${data.cpu_freq} MHz` : '—');
        const cpuBar = document.querySelector('[data-field="cpu_percent_bar"]');
        if (cpuBar) cpuBar.style.width = `${data.cpu_percent ?? 0}%`;

        updateField('ram_percent', data.ram_percent ?? '—');
        updateField('ram_used', data.ram_used);
        updateField('ram_free', data.ram_free);
        const ramBar = document.querySelector('[data-field="ram_percent_bar"]');
        if (ramBar) ramBar.style.width = `${data.ram_percent ?? 0}%`;

        updateField('disk_percent', data.disk_percent ?? '—');
        updateField('disk_used', data.disk_used);
        updateField('disk_free', data.disk_free);
        const diskBar = document.querySelector('[data-field="disk_percent_bar"]');
        if (diskBar) diskBar.style.width = `${data.disk_percent ?? 0}%`;

        updateField('sys_uptime', data.sys_uptime);
        updateField('sys_hostname', data.sys_hostname);
        updateField('sys_lan_ip', data.sys_lan_ip || '—');
        updateField('sys_public_ip', data.sys_public_ip || '—');
        updateField('sys_os', data.sys_os);
        updateField('sys_kernel', data.sys_kernel);
        updateField('sys_arch', data.sys_arch);

        updateField('net_down', data.net_down);
        updateField('net_up', data.net_up);
        updateField('net_connections', data.net_connections);
        updateField('net_daily_rx', data.net_daily_rx);
        updateField('net_daily_tx', data.net_daily_tx);
    }).catch(() => {});
}

/* ---------- Powiadomienia ---------- */
let knownAlertIds = new Set();

function refreshNotifications() {
    const bell = document.getElementById('alertBell');
    if (!bell) return;

    apiFetch('/api/notifications.php').then((data) => {
        const countBadge = document.getElementById('alertCount');
        const dropdown = document.getElementById('alertDropdown');

        countBadge.textContent = data.count;
        countBadge.classList.toggle('d-none', data.count === 0);

        if (data.alerts.length === 0) {
            dropdown.innerHTML = '<div class="text-muted small px-2 py-3 text-center">Brak aktywnych alarmów</div>';
        } else {
            dropdown.innerHTML = data.alerts.map((alert) => `
                <div class="d-flex justify-content-between align-items-start gap-2 border-bottom border-secondary-subtle py-2 px-2">
                    <div>
                        <div class="small fw-semibold text-${severityClass(alert.severity)}">${escapeHtml(alert.type)}</div>
                        <div class="small text-muted">${escapeHtml(alert.message)}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary ack-btn" data-id="${alert.id}" title="Potwierdź"><i class="bi bi-check2"></i></button>
                </div>
            `).join('');
        }

        // Toast dla nowych alarmów.
        data.alerts.forEach((alert) => {
            if (!knownAlertIds.has(alert.id)) {
                showToast(escapeHtml(alert.message), severityClass(alert.severity));
            }
        });
        knownAlertIds = new Set(data.alerts.map((a) => a.id));

        dropdown.querySelectorAll('.ack-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                apiPost('/api/notifications.php', { id: btn.dataset.id }).then(refreshNotifications);
            });
        });
    }).catch(() => {});
}

function severityClass(severity) {
    if (severity === 'critical') return 'danger';
    if (severity === 'warning') return 'warning';
    return 'info';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/* ---------- Start pollingu ---------- */
refreshDashboardTiles();
refreshNotifications();
setInterval(refreshDashboardTiles, REFRESH_INTERVAL_MS);
setInterval(refreshNotifications, REFRESH_INTERVAL_MS);

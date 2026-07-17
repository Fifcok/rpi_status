/** Odświeżanie sekcji bezpieczeństwa: logowania, firewall, fail2ban. */

function loadSecurity() {
    apiFetch('/api/security.php').then((data) => {
        renderLoginRows('recentLoginsBody', data.recent_logins);
        renderLoginRows('failedLoginsBody', data.failed_logins);
        renderAttackers(data.top_attackers);
        renderUsernames(data.top_usernames);
        renderFirewall(data.firewall);
        renderFail2ban(data.fail2ban);
    }).catch(() => {});
}

function renderLoginRows(tbodyId, logins) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;
    if (!logins || logins.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>';
        return;
    }
    tbody.innerHTML = logins.map((l) => `<tr><td>${escapeHtml(l.user)}</td><td>${escapeHtml(l.ip)}</td></tr>`).join('');
}

function renderAttackers(attackers) {
    const tbody = document.getElementById('attackersBody');
    if (!tbody) return;
    if (!attackers || attackers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>';
        return;
    }
    tbody.innerHTML = attackers.map((a) => `<tr><td>${escapeHtml(a.ip)}</td><td>${a.attempts}</td></tr>`).join('');
}

function renderUsernames(usernames) {
    const tbody = document.getElementById('usernamesBody');
    if (!tbody) return;
    if (!usernames || usernames.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>';
        return;
    }
    tbody.innerHTML = usernames.map((u) => `<tr><td>${escapeHtml(u.user)}</td><td>${u.attempts}</td></tr>`).join('');
}

function renderFirewall(firewall) {
    const el = document.getElementById('firewallStatus');
    if (!el) return;
    const cls = firewall.active ? 'text-bg-success' : 'text-bg-danger';
    const state = firewall.active === null ? 'nieznany' : (firewall.active ? 'aktywny' : 'nieaktywny');
    el.innerHTML = `<span class="badge ${cls}">${escapeHtml(firewall.engine)}: ${state}</span>`;
}

function renderFail2ban(fail2ban) {
    const el = document.getElementById('fail2banStatus');
    if (!el) return;
    if (!fail2ban.installed) {
        el.innerHTML = '<span class="badge text-bg-secondary">niezainstalowany</span>';
        return;
    }
    const cls = fail2ban.active ? 'text-bg-success' : 'text-bg-danger';
    const state = fail2ban.active ? 'aktywny' : 'nieaktywny';
    const jails = fail2ban.jails.length ? fail2ban.jails.join(', ') : 'brak jaili';
    el.innerHTML = `<span class="badge ${cls}">${state}</span><span class="text-muted small ms-2">${escapeHtml(jails)}</span>`;
}

document.getElementById('refreshSecurity')?.addEventListener('click', loadSecurity);

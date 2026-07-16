/** Uruchamianie backupu i odświeżanie statusu. */

function loadBackupStatus() {
    apiFetch('/api/backup_status.php').then((data) => {
        const last = data.last;
        document.getElementById('lastBackupDate').textContent = last ? formatDate(last.started_at) : 'Brak';
        document.getElementById('lastBackupSize').textContent = last && last.size_bytes ? formatBytes(last.size_bytes) : '—';

        const statusBadge = document.getElementById('lastBackupStatus');
        if (last) {
            const cls = last.status === 'success' ? 'text-bg-success' : (last.status === 'running' ? 'text-bg-warning' : 'text-bg-danger');
            statusBadge.innerHTML = `<span class="badge ${cls}">${escapeHtml(last.status)}</span>`;
        }

        renderBackupHistory(data.history);
    }).catch(() => {});
}

function renderBackupHistory(history) {
    const tbody = document.querySelector('#backupHistoryTable tbody');
    if (!tbody) return;

    if (history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Brak wykonanych backupów.</td></tr>';
        return;
    }

    tbody.innerHTML = history.map((entry) => {
        const duration = entry.finished_at ? `${entry.finished_at - entry.started_at}s` : '—';
        const cls = entry.status === 'success' ? 'text-bg-success' : (entry.status === 'running' ? 'text-bg-warning' : 'text-bg-danger');
        return `
            <tr>
                <td>${formatDate(entry.started_at)}</td>
                <td>${duration}</td>
                <td>${entry.size_bytes ? formatBytes(entry.size_bytes) : '—'}</td>
                <td><span class="badge ${cls}">${escapeHtml(entry.status)}</span></td>
                <td class="text-muted small">${escapeHtml(entry.message ?? '')}</td>
            </tr>
        `;
    }).join('');
}

function formatDate(unixSeconds) {
    const d = new Date(unixSeconds * 1000);
    return d.toLocaleString('pl-PL');
}

function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    return `${value.toFixed(1)} ${units[i]}`;
}

document.getElementById('runBackup')?.addEventListener('click', (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Backup w toku…';

    apiPost('/api/backup_run.php').then((res) => {
        showToast(res.message, res.success ? 'success' : 'danger');
        loadBackupStatus();
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill"></i> Uruchom backup';
    });
});

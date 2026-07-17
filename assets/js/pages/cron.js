/** Odświeżanie listy zadań cron. */

function renderCronJobs(jobs) {
    const tbody = document.querySelector('#cronTable tbody');
    if (!tbody) return;

    if (jobs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Brak zdefiniowanych zadań cron.</td></tr>';
        return;
    }

    tbody.innerHTML = jobs.map((job) => `
        <tr>
            <td><code>${escapeHtml(job.schedule)}</code></td>
            <td class="text-truncate" style="max-width: 320px;" title="${escapeHtml(job.command)}">${escapeHtml(job.command)}</td>
            <td>${escapeHtml(job.user)}</td>
            <td class="text-muted small">${escapeHtml(job.source)}</td>
            <td>${escapeHtml(job.last_run ?? '—')}</td>
            <td><span class="badge ${job.status === 'ok' ? 'text-bg-success' : 'text-bg-secondary'}">${escapeHtml(job.status)}</span></td>
        </tr>
    `).join('');
}

function renderCronConsole(entries) {
    const el = document.getElementById('cronConsole');
    if (!el) return;

    if (!entries || entries.length === 0) {
        el.innerHTML = '<div class="text-muted">Brak danych w logach systemowych (journalctl/syslog).</div>';
        return;
    }

    el.innerHTML = entries.map((e) => {
        const time = escapeHtml(e.time ?? '?');
        const user = escapeHtml(e.user ?? '?');
        const command = escapeHtml(e.command ?? e.raw);
        return `<div class="console-line">[${time}] <strong>${user}</strong>: ${command}</div>`;
    }).join('');
}

function loadCronJobs() {
    apiFetch('/api/cron_list.php').then((data) => {
        renderCronJobs(data.jobs);
        renderCronConsole(data.recent);
    }).catch(() => {});
}

document.getElementById('refreshCron')?.addEventListener('click', loadCronJobs);

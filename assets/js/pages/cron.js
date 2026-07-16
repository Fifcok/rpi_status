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

function loadCronJobs() {
    apiFetch('/api/cron_list.php').then((data) => renderCronJobs(data.jobs)).catch(() => {});
}

document.getElementById('refreshCron')?.addEventListener('click', loadCronJobs);

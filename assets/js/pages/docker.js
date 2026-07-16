/** Lista kontenerów Docker + akcje start/stop/restart/logi. */

function renderContainers(containers) {
    const tbody = document.getElementById('dockerTableBody');
    if (!tbody) return;

    if (containers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Brak kontenerów.</td></tr>';
        return;
    }

    tbody.innerHTML = containers.map((c) => `
        <tr data-id="${c.id}">
            <td><span class="status-dot ${c.running ? 'status-green' : 'status-red'}"></span></td>
            <td>${escapeHtml(c.name)}</td>
            <td class="text-muted small">${escapeHtml(c.image)}</td>
            <td class="small">${escapeHtml(c.status_raw)}</td>
            <td>${c.cpu_percent ?? '—'}</td>
            <td>${c.ram_usage ?? '—'}</td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-success" data-action="start" title="Start"><i class="bi bi-play-fill"></i></button>
                    <button class="btn btn-outline-warning" data-action="stop" title="Stop"><i class="bi bi-stop-fill"></i></button>
                    <button class="btn btn-outline-info" data-action="restart" title="Restart"><i class="bi bi-arrow-repeat"></i></button>
                    <button class="btn btn-outline-secondary" data-action="logs" title="Logi"><i class="bi bi-file-text"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

function loadContainers() {
    apiFetch('/api/docker_list.php').then((data) => renderContainers(data.containers)).catch(() => {});
}

document.getElementById('refreshDocker')?.addEventListener('click', loadContainers);

document.getElementById('dockerTableBody')?.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const row = btn.closest('tr');
    const containerId = row.dataset.id;
    const action = btn.dataset.action;
    const name = row.querySelector('td:nth-child(2)').textContent;

    if (action === 'logs') {
        showContainerLogs(containerId, name);
        return;
    }

    btn.disabled = true;
    apiPost('/api/docker_action.php', { container: containerId, action })
        .then((res) => {
            showToast(res.message, res.success ? 'success' : 'danger');
            loadContainers();
        })
        .finally(() => { btn.disabled = false; });
});

function showContainerLogs(containerId, name) {
    const modalEl = document.getElementById('logsModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('logsModalContainer').textContent = name;
    document.getElementById('logsModalContent').textContent = 'Ładowanie…';
    modal.show();

    apiFetch(`/api/docker_logs.php?container=${encodeURIComponent(containerId)}`).then((data) => {
        document.getElementById('logsModalContent').textContent = data.logs || 'Brak logów.';
    });
}

if (document.getElementById('dockerTableBody')) {
    setInterval(loadContainers, 10000);
}

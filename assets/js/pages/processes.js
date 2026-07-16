/** Odświeżanie listy top procesów. */

function renderProcesses(processes) {
    const tbody = document.querySelector('#processesTable tbody');
    if (!tbody) return;

    tbody.innerHTML = processes.map((p) => `
        <tr>
            <td>${p.pid}</td>
            <td>${escapeHtml(p.name)}</td>
            <td>${p.cpu_percent}</td>
            <td>${p.ram_percent}</td>
            <td>${formatBytesLocal2(p.ram_bytes)}</td>
            <td>${escapeHtml(p.elapsed)}</td>
        </tr>
    `).join('');
}

function formatBytesLocal2(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Number(bytes) || 0;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    return `${value.toFixed(1)} ${units[i]}`;
}

function loadProcesses() {
    apiFetch('/api/processes.php').then((data) => renderProcesses(data.processes)).catch(() => {});
}

document.getElementById('refreshProcesses')?.addEventListener('click', loadProcesses);
if (document.getElementById('processesTable')) {
    setInterval(loadProcesses, 5000);
}

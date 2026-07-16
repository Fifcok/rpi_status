/** Odświeżanie statusu usług (co 5s + przycisk ręczny). */

function renderServices(services) {
    const tbody = document.querySelector('#servicesTable tbody');
    if (!tbody) return;
    tbody.innerHTML = services.map((s) => `
        <tr data-unit="${s.unit}">
            <td><span class="status-dot ${s.active ? 'status-green' : 'status-red'}"></span></td>
            <td>${s.label}</td>
            <td class="text-muted">${s.unit}</td>
            <td><span class="badge ${s.active ? 'text-bg-success' : 'text-bg-danger'}">${s.state}</span></td>
        </tr>
    `).join('');
}

function loadServices() {
    apiFetch('/api/services.php').then((data) => renderServices(data.services)).catch(() => {});
}

document.getElementById('refreshServices')?.addEventListener('click', loadServices);
setInterval(loadServices, 5000);

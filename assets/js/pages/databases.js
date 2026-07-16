/** Lista baz danych MySQL/MariaDB + podgląd największych tabel po kliknięciu. */

function loadDatabases() {
    apiFetch('/api/databases.php').then((data) => renderDatabases(data.databases)).catch(() => {});
}

function renderDatabases(databases) {
    const tbody = document.querySelector('#databasesTable tbody');
    if (!tbody) return;

    if (databases.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Brak danych.</td></tr>';
        return;
    }

    tbody.innerHTML = databases.map((db) => `
        <tr class="db-row" role="button" data-db="${escapeHtml(db.db_name)}">
            <td>${escapeHtml(db.db_name)}</td>
            <td>${db.table_count}</td>
            <td>${formatBytesLocal(db.size_bytes)}</td>
        </tr>
    `).join('');
}

function formatBytesLocal(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Number(bytes) || 0;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    return `${value.toFixed(1)} ${units[i]}`;
}

document.getElementById('refreshDatabases')?.addEventListener('click', loadDatabases);

document.querySelector('#databasesTable')?.addEventListener('click', (e) => {
    const row = e.target.closest('.db-row');
    if (!row) return;
    loadTables(row.dataset.db);
});

function loadTables(dbName) {
    document.getElementById('selectedDbLabel').textContent = `— ${dbName}`;
    const tbody = document.getElementById('tablesTableBody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Ładowanie…</td></tr>';

    apiFetch(`/api/databases.php?db=${encodeURIComponent(dbName)}`).then((data) => {
        if (data.tables.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Brak tabel.</td></tr>';
            return;
        }
        tbody.innerHTML = data.tables.map((t) => `
            <tr>
                <td>${escapeHtml(t.table_name)}</td>
                <td>${t.table_rows ?? '—'}</td>
                <td>${formatBytesLocal(t.size_bytes)}</td>
            </tr>
        `).join('');
    });
}

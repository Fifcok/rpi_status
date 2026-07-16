/** Przeglądarka logów: wybór źródła, filtr poziomu, wyszukiwanie. */

function loadLogs() {
    const source = document.getElementById('logSource').value;
    const level = document.getElementById('logLevel').value;
    const search = document.getElementById('logSearch').value;

    const params = new URLSearchParams({ source, level, search });
    const output = document.getElementById('logOutput');
    output.textContent = 'Ładowanie…';

    apiFetch(`/api/logs.php?${params.toString()}`).then((data) => {
        document.getElementById('logCount').textContent =
            `${data.filtered_count} / ${data.total_lines} linii — źródło: ${data.label}`;

        if (data.lines.length === 0) {
            output.textContent = 'Brak wpisów spełniających kryteria.';
            return;
        }

        output.innerHTML = data.lines.map((entry) =>
            `<span class="log-line-${entry.level}">${escapeHtml(entry.line)}</span>`
        ).join('\n');
    }).catch(() => { output.textContent = 'Błąd podczas pobierania logów.'; });
}

document.getElementById('logReload')?.addEventListener('click', loadLogs);
document.getElementById('logSource')?.addEventListener('change', loadLogs);
document.getElementById('logLevel')?.addEventListener('change', loadLogs);
document.getElementById('logSearch')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') loadLogs();
});

if (document.getElementById('logOutput')) {
    loadLogs();
}

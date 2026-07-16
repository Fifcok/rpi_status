/**
 * Wykresy historii (CPU, RAM, temperatura, dysk) na dashboardzie, z przełącznikiem
 * zakresu 24h / 7 dni / 30 dni. Dane pochodzą z /api/history.php (zapisywane co
 * minutę przez cron/collect_history.php).
 */

const CHART_COLORS = {
    cpu: '#2dd4bf',
    ram: '#60a5fa',
    temp: '#f97316',
    disk: '#eab308',
};

function buildChart(canvasId, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label,
                data: [],
                borderColor: color,
                backgroundColor: color + '33',
                fill: true,
                tension: 0.35,
                pointRadius: 0,
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            animation: { duration: 400 },
            scales: {
                x: { ticks: { color: '#8b949e', maxTicksLimit: 8 }, grid: { color: '#2a323c' } },
                y: { ticks: { color: '#8b949e' }, grid: { color: '#2a323c' }, beginAtZero: true },
            },
            plugins: { legend: { display: false } },
        },
    });
}

const charts = {
    cpu: buildChart('chartCpu', 'CPU %', CHART_COLORS.cpu),
    ram: buildChart('chartRam', 'RAM %', CHART_COLORS.ram),
    temp: buildChart('chartTemp', 'Temp °C', CHART_COLORS.temp),
    disk: buildChart('chartDisk', 'Dysk %', CHART_COLORS.disk),
};

let currentRange = '24h';

function loadHistory(range) {
    apiFetch(`/api/history.php?range=${encodeURIComponent(range)}`).then((data) => {
        Object.entries(charts).forEach(([key, chart]) => {
            if (!chart) return;
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data[key];
            chart.update();
        });
    }).catch(() => {});
}

const rangeSelector = document.getElementById('rangeSelector');
if (rangeSelector) {
    rangeSelector.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', () => {
            rangeSelector.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            currentRange = btn.dataset.range;
            loadHistory(currentRange);
        });
    });

    loadHistory(currentRange);
    setInterval(() => loadHistory(currentRange), 60000);
}

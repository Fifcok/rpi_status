<?php
/**
 * Strona główna: kafelki CPU/RAM/Dysk/System/Sieć + wykresy historii.
 * Dane początkowe renderowane są server-side, a następnie odświeżane co 5s przez AJAX (assets/js/app.js).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

require_once __DIR__ . '/includes/system_info.php';

$cpu = get_cpu_info();
$ram = get_ram_info();
$disks = get_all_disks_info();
$system = get_system_info();
$network = get_network_info();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageScripts = ['/assets/js/charts.js'];

require APP_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Dashboard</h1>
    <div class="text-muted small"><i class="bi bi-arrow-repeat"></i> Automatyczne odświeżanie co 5s</div>
</div>

<div class="tile-grid" id="dashboardTiles">

    <!-- CPU -->
    <div class="tile card" data-tile="cpu">
        <div class="card-body">
            <div class="tile-header">
                <i class="bi bi-cpu tile-icon"></i>
                <span class="tile-title">CPU</span>
            </div>
            <div class="tile-value"><span data-field="cpu_percent"><?= h((string) ($cpu['percent'] ?? '—')) ?></span><small>%</small></div>
            <div class="progress tile-progress">
                <div class="progress-bar bg-accent" data-field="cpu_percent_bar" style="width: <?= h((string) ($cpu['percent'] ?? 0)) ?>%"></div>
            </div>
            <div class="tile-meta">
                <div><i class="bi bi-diagram-3"></i> Rdzenie: <span data-field="cpu_cores"><?= h((string) $cpu['cores']) ?></span></div>
                <div><i class="bi bi-thermometer-half"></i> Temp.: <span data-field="cpu_temp"><?= h($cpu['temp'] !== null ? $cpu['temp'] . ' °C' : '—') ?></span></div>
                <div><i class="bi bi-speedometer"></i> Takt: <span data-field="cpu_freq"><?= h($cpu['freq_mhz'] !== null ? $cpu['freq_mhz'] . ' MHz' : '—') ?></span></div>
            </div>
        </div>
    </div>

    <!-- RAM -->
    <div class="tile card" data-tile="ram">
        <div class="card-body">
            <div class="tile-header">
                <i class="bi bi-memory tile-icon"></i>
                <span class="tile-title">RAM</span>
            </div>
            <div class="tile-value"><span data-field="ram_percent"><?= h((string) ($ram['percent'] ?? '—')) ?></span><small>%</small></div>
            <div class="progress tile-progress">
                <div class="progress-bar bg-info" data-field="ram_percent_bar" style="width: <?= h((string) ($ram['percent'] ?? 0)) ?>%"></div>
            </div>
            <div class="tile-meta">
                <div><i class="bi bi-box"></i> Zajęte: <span data-field="ram_used"><?= h(format_bytes($ram['used'])) ?></span></div>
                <div><i class="bi bi-box2"></i> Wolne: <span data-field="ram_free"><?= h(format_bytes($ram['free'])) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Dysk(i) -->
    <div class="tile card tile-wide" data-tile="disk">
        <div class="card-body">
            <div class="tile-header">
                <i class="bi bi-hdd-stack tile-icon"></i>
                <span class="tile-title">Dyski</span>
            </div>
            <div id="diskList" class="disk-list">
                <?php foreach ($disks as $d): ?>
                <div class="disk-row">
                    <div class="disk-row-head">
                        <span><i class="bi bi-hdd"></i> <?= h($d['label']) ?> <span class="text-muted small">(<?= h($d['mount']) ?>)</span></span>
                        <span class="fw-semibold"><?= h((string) $d['percent']) ?>%</span>
                    </div>
                    <div class="progress tile-progress">
                        <div class="progress-bar <?= $d['percent'] >= 90 ? 'bg-danger' : 'bg-warning' ?>" style="width: <?= h((string) $d['percent']) ?>%"></div>
                    </div>
                    <div class="tile-meta">
                        <div>Zajęte: <?= h(format_bytes($d['used'])) ?> / <?= h(format_bytes($d['total'])) ?> — Wolne: <?= h(format_bytes($d['free'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$disks): ?>
                <div class="text-muted small">Nie wykryto zamontowanych dysków.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- System -->
    <div class="tile card tile-wide" data-tile="system">
        <div class="card-body">
            <div class="tile-header">
                <i class="bi bi-motherboard tile-icon"></i>
                <span class="tile-title">System</span>
            </div>
            <div class="tile-meta tile-meta-grid">
                <div><i class="bi bi-clock"></i> Uptime: <span data-field="sys_uptime"><?= h($system['uptime_human']) ?></span></div>
                <div><i class="bi bi-pc-display"></i> Hostname: <span data-field="sys_hostname"><?= h($system['hostname']) ?></span></div>
                <div><i class="bi bi-diagram-2"></i> IP LAN: <span data-field="sys_lan_ip"><?= h($system['lan_ip'] ?: '—') ?></span></div>
                <div><i class="bi bi-globe"></i> IP publiczne: <span data-field="sys_public_ip">ładowanie…</span></div>
                <div><i class="bi bi-info-circle"></i> OS: <span data-field="sys_os"><?= h($system['os_version']) ?></span></div>
                <div><i class="bi bi-gear"></i> Kernel: <span data-field="sys_kernel"><?= h($system['kernel']) ?></span></div>
                <div><i class="bi bi-cpu"></i> Architektura: <span data-field="sys_arch"><?= h($system['arch']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Sieć -->
    <div class="tile card tile-wide" data-tile="network">
        <div class="card-body">
            <div class="tile-header">
                <i class="bi bi-router tile-icon"></i>
                <span class="tile-title">Sieć</span>
            </div>
            <div class="tile-meta tile-meta-grid">
                <div><i class="bi bi-arrow-down-circle text-success"></i> Download: <span data-field="net_down"><?= h(format_bytes($network['download_bps'])) ?>/s</span></div>
                <div><i class="bi bi-arrow-up-circle text-danger"></i> Upload: <span data-field="net_up"><?= h(format_bytes($network['upload_bps'])) ?>/s</span></div>
                <div><i class="bi bi-diagram-3"></i> Połączenia: <span data-field="net_connections"><?= h((string) $network['connections']) ?></span></div>
                <div><i class="bi bi-calendar-day"></i> Dzienny RX: <span data-field="net_daily_rx"><?= h(format_bytes($network['daily_rx'])) ?></span></div>
                <div><i class="bi bi-calendar-day"></i> Dzienny TX: <span data-field="net_daily_tx"><?= h(format_bytes($network['daily_tx'])) ?></span></div>
            </div>
        </div>
    </div>

</div>

<div class="card chart-card mt-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h2 class="h6 mb-0"><i class="bi bi-graph-up"></i> Historia metryk</h2>
            <div class="btn-group btn-group-sm" role="group" id="rangeSelector">
                <button class="btn btn-outline-secondary active" data-range="24h">24h</button>
                <button class="btn btn-outline-secondary" data-range="7d">7 dni</button>
                <button class="btn btn-outline-secondary" data-range="30d">30 dni</button>
            </div>
        </div>
        <div class="chart-grid">
            <div class="chart-box">
                <div class="chart-box-title">CPU (%)</div>
                <canvas id="chartCpu"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-box-title">RAM (%)</div>
                <canvas id="chartRam"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-box-title">Temperatura (°C)</div>
                <canvas id="chartTemp"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-box-title">Dysk (%)</div>
                <canvas id="chartDisk"></canvas>
            </div>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

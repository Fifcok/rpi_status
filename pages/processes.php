<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/process_info.php';

$processes = get_top_processes(20);

$pageTitle = 'Procesy';
$activePage = 'processes';
$pageScripts = ['/assets/js/pages/processes.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Top 20 procesów</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshProcesses"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="processesTable">
            <thead>
                <tr><th>PID</th><th>Nazwa</th><th>CPU %</th><th>RAM %</th><th>RAM</th><th>Czas działania</th></tr>
            </thead>
            <tbody>
            <?php foreach ($processes as $p): ?>
                <tr>
                    <td><?= h((string) $p['pid']) ?></td>
                    <td><?= h($p['name']) ?></td>
                    <td><?= h((string) $p['cpu_percent']) ?></td>
                    <td><?= h((string) $p['ram_percent']) ?></td>
                    <td><?= h(format_bytes($p['ram_bytes'])) ?></td>
                    <td><?= h($p['elapsed']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

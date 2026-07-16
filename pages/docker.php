<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/docker_info.php';

$dockerAvailable = docker_available();
$containers = $dockerAvailable ? get_docker_containers() : [];

$pageTitle = 'Docker';
$activePage = 'docker';
$pageScripts = ['/assets/js/pages/docker.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Docker</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshDocker">
        <i class="bi bi-arrow-clockwise"></i> Odśwież
    </button>
</div>

<?php if (!$dockerAvailable): ?>
<div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i> Docker nie jest zainstalowany lub demon nie odpowiada na tym systemie.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="dockerTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Nazwa</th>
                    <th>Obraz</th>
                    <th>Uptime</th>
                    <th>CPU</th>
                    <th>RAM</th>
                    <th class="text-end">Akcje</th>
                </tr>
            </thead>
            <tbody id="dockerTableBody">
            <?php foreach ($containers as $c): ?>
                <tr data-id="<?= h($c['id']) ?>">
                    <td><span class="status-dot <?= $c['running'] ? 'status-green' : 'status-red' ?>"></span></td>
                    <td><?= h($c['name']) ?></td>
                    <td class="text-muted small"><?= h($c['image']) ?></td>
                    <td class="small"><?= h($c['status_raw']) ?></td>
                    <td><?= h((string) ($c['cpu_percent'] ?? '—')) ?></td>
                    <td><?= h((string) ($c['ram_usage'] ?? '—')) ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success" data-action="start" title="Start"><i class="bi bi-play-fill"></i></button>
                            <button class="btn btn-outline-warning" data-action="stop" title="Stop"><i class="bi bi-stop-fill"></i></button>
                            <button class="btn btn-outline-info" data-action="restart" title="Restart"><i class="bi bi-arrow-repeat"></i></button>
                            <button class="btn btn-outline-secondary" data-action="logs" title="Logi"><i class="bi bi-file-text"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($dockerAvailable && !$containers): ?>
        <div class="text-muted text-center py-4">Brak kontenerów.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Logi kontenera: <span id="logsModalContainer"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre class="log-viewer" id="logsModalContent">Ładowanie…</pre>
            </div>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/services_info.php';

$services = get_all_services_status();

$pageTitle = 'Monitoring usług';
$activePage = 'services';
$pageScripts = ['/assets/js/pages/services.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Monitoring usług</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshServices">
        <i class="bi bi-arrow-clockwise"></i> Odśwież
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="servicesTable">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Usługa</th>
                    <th>Jednostka systemd</th>
                    <th>Stan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $service): ?>
                <tr data-unit="<?= h($service['unit']) ?>">
                    <td><span class="status-dot <?= $service['active'] ? 'status-green' : 'status-red' ?>"></span></td>
                    <td><?= h($service['label']) ?></td>
                    <td class="text-muted"><?= h($service['unit']) ?></td>
                    <td><span class="badge <?= $service['active'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= h($service['state']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

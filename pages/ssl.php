<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/ssl_info.php';

$certificates = get_ssl_certificates();

$pageTitle = 'SSL';
$activePage = 'ssl';
$pageScripts = ['/assets/js/pages/ssl.js'];

require APP_ROOT . '/includes/header.php';

$statusClass = ['green' => 'text-bg-success', 'yellow' => 'text-bg-warning', 'red' => 'text-bg-danger'];
$statusLabel = ['green' => 'OK', 'yellow' => 'Wygasa wkrótce', 'red' => 'Krytyczne'];
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Certyfikaty SSL</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshSsl"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="sslTable">
            <thead>
                <tr>
                    <th>Domena</th>
                    <th>Wystawca</th>
                    <th>Ważny do</th>
                    <th>Dni pozostało</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($certificates as $cert): ?>
                <tr>
                    <td><?= h($cert['domain']) ?></td>
                    <td class="text-muted small"><?= h($cert['issuer']) ?></td>
                    <td><?= h($cert['valid_until']) ?></td>
                    <td><?= h((string) $cert['days_left']) ?></td>
                    <td><span class="badge <?= $statusClass[$cert['status']] ?>"><?= $statusLabel[$cert['status']] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$certificates): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Nie znaleziono certyfikatów (sprawdzono /etc/letsencrypt/live).</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

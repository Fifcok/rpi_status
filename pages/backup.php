<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/backup_info.php';

$last = get_last_backup();
$history = get_backup_history(20);

$pageTitle = 'Backup';
$activePage = 'backup';
$pageScripts = ['/assets/js/pages/backup.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Backup</h1>
    <button class="btn btn-accent btn-sm" id="runBackup"><i class="bi bi-play-fill"></i> Uruchom backup</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Ostatni backup</div>
            <div class="h5 mb-0" id="lastBackupDate"><?= h($last ? date('Y-m-d H:i', (int) $last['started_at']) : 'Brak') ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Rozmiar</div>
            <div class="h5 mb-0" id="lastBackupSize"><?= h($last && $last['size_bytes'] ? format_bytes((int) $last['size_bytes']) : '—') ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Status</div>
            <div class="h5 mb-0" id="lastBackupStatus">
                <?php if ($last): ?>
                    <span class="badge <?= $last['status'] === 'success' ? 'text-bg-success' : ($last['status'] === 'running' ? 'text-bg-warning' : 'text-bg-danger') ?>"><?= h($last['status']) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">brak danych</span>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="alert alert-secondary small">
    <i class="bi bi-info-circle"></i> Backup obejmuje: pliki <strong>www</strong> (<?= h(BACKUP_TARGETS['www']) ?>), bazy danych MySQL/MariaDB (mysqldump) oraz konfigurację (<?= h(BACKUP_TARGETS['config']) ?>). Archiwum zapisywane jest w katalogu <code>backup/</code>.
</div>

<div class="card">
    <div class="card-header">Historia backupów</div>
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="backupHistoryTable">
            <thead>
                <tr>
                    <th>Data rozpoczęcia</th>
                    <th>Czas trwania</th>
                    <th>Rozmiar</th>
                    <th>Status</th>
                    <th>Wiadomość</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $entry):
                $duration = $entry['finished_at'] ? ((int) $entry['finished_at'] - (int) $entry['started_at']) . 's' : '—';
            ?>
                <tr>
                    <td><?= h(date('Y-m-d H:i:s', (int) $entry['started_at'])) ?></td>
                    <td><?= h($duration) ?></td>
                    <td><?= h($entry['size_bytes'] ? format_bytes((int) $entry['size_bytes']) : '—') ?></td>
                    <td>
                        <span class="badge <?= $entry['status'] === 'success' ? 'text-bg-success' : ($entry['status'] === 'running' ? 'text-bg-warning' : 'text-bg-danger') ?>">
                            <?= h($entry['status']) ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= h((string) ($entry['message'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Brak wykonanych backupów.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

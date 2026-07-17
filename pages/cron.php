<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/cron_info.php';

$jobs = get_cron_jobs();
$recentLog = get_recent_cron_log(15);

$pageTitle = 'Cron';
$activePage = 'cron';
$pageScripts = ['/assets/js/pages/cron.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Zadania Cron</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshCron"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-terminal"></i> Konsola — ostatnie 15 wykonanych poleceń</div>
    <div class="card-body">
        <div class="log-viewer" id="cronConsole">
            <?php if (!$recentLog): ?>
            <div class="text-muted">Brak danych w logach systemowych (journalctl/syslog).</div>
            <?php else: foreach ($recentLog as $entry): ?>
            <div class="console-line">[<?= h($entry['time'] ?? '?') ?>] <strong><?= h($entry['user'] ?? '?') ?></strong>: <?= h($entry['command'] ?? $entry['raw']) ?></div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-dark-custom mb-0" id="cronTable">
            <thead>
                <tr>
                    <th>Harmonogram</th>
                    <th>Polecenie</th>
                    <th>Użytkownik</th>
                    <th>Źródło</th>
                    <th>Ostatnie wykonanie</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><code><?= h($job['schedule']) ?></code></td>
                    <td class="text-truncate" style="max-width: 320px;" title="<?= h($job['command']) ?>"><?= h($job['command']) ?></td>
                    <td><?= h($job['user']) ?></td>
                    <td class="text-muted small"><?= h($job['source']) ?></td>
                    <td><?= h($job['last_run'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $job['status'] === 'ok' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= h($job['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$jobs): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Brak zdefiniowanych zadań cron.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

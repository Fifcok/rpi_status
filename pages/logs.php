<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Logi';
$activePage = 'logs';
$pageScripts = ['/assets/js/pages/logs.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Logi systemowe</h1>
    <div class="text-muted small">Ostatnie 1000 linii</div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">Źródło</label>
                <select class="form-select form-select-sm" id="logSource">
                    <?php foreach (LOG_SOURCES as $key => $source): ?>
                        <option value="<?= h($key) ?>"><?= h($source['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Poziom</label>
                <select class="form-select form-select-sm" id="logLevel">
                    <option value="">Wszystkie</option>
                    <option value="INFO">INFO</option>
                    <option value="WARNING">WARNING</option>
                    <option value="ERROR">ERROR</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small mb-1">Szukaj</label>
                <input type="text" class="form-control form-control-sm" id="logSearch" placeholder="Fraza do wyszukania…">
            </div>
            <div class="col-auto">
                <button class="btn btn-accent btn-sm" id="logReload"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between small text-muted mb-2">
            <span id="logCount">—</span>
        </div>
        <pre class="log-viewer" id="logOutput">Ładowanie…</pre>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/mysql_info.php';

$available = mysql_available();
$databases = $available ? get_mysql_databases() : [];

$pageTitle = 'Bazy danych';
$activePage = 'databases';
$pageScripts = ['/assets/js/pages/databases.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Bazy danych (MySQL / MariaDB)</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshDatabases"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
</div>

<?php if (!$available): ?>
<div class="alert alert-secondary">
    <i class="bi bi-info-circle"></i> Brak połączenia z MySQL/MariaDB. Skonfiguruj dane logowania w <code>config.php</code> (DB_MYSQL_*).
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Bazy danych</div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0" id="databasesTable">
                    <thead><tr><th>Nazwa</th><th>Tabele</th><th>Rozmiar</th></tr></thead>
                    <tbody>
                    <?php foreach ($databases as $db): ?>
                        <tr class="db-row" role="button" data-db="<?= h($db['db_name']) ?>">
                            <td><?= h($db['db_name']) ?></td>
                            <td><?= h((string) $db['table_count']) ?></td>
                            <td><?= h(format_bytes((float) $db['size_bytes'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$databases): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Brak danych.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Największe tabele <span class="text-muted small" id="selectedDbLabel"></span></div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0" id="tablesTable">
                    <thead><tr><th>Tabela</th><th>Wiersze</th><th>Rozmiar</th></tr></thead>
                    <tbody id="tablesTableBody">
                        <tr><td colspan="3" class="text-center text-muted py-4">Wybierz bazę z listy po lewej.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/footer.php'; ?>

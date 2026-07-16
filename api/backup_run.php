<?php
/**
 * POST /api/backup_run.php - uruchamia pełny backup (www + bazy danych + konfiguracja).
 * Operacja jest synchroniczna i może potrwać dłużej dla dużych zbiorów danych,
 * dlatego podnosimy limit czasu wykonania tylko dla tego żądania.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/backup_info.php';
require_once __DIR__ . '/../includes/mysql_info.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metoda niedozwolona.'], 405);
}

csrf_require();

set_time_limit(0);
ignore_user_abort(true);

$result = run_full_backup();

app_log('info', 'Backup uruchomiony ręcznie przez ' . (current_user()['username'] ?? '?') . ': ' . ($result['success'] ? 'sukces' : 'błąd - ' . $result['message']));

json_response($result, $result['success'] ? 200 : 500);

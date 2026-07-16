<?php
/**
 * GET /api/databases.php - lista baz MySQL/MariaDB.
 * GET /api/databases.php?db=nazwa - N największych tabel danej bazy.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/mysql_info.php';

$db = trim((string) ($_GET['db'] ?? ''));

if ($db !== '') {
    if (!is_safe_identifier($db)) {
        json_response(['error' => 'Nieprawidłowa nazwa bazy.'], 400);
    }
    json_response(['database' => $db, 'tables' => get_mysql_largest_tables($db, 15)]);
}

json_response([
    'available' => mysql_available(),
    'databases' => get_mysql_databases(),
]);

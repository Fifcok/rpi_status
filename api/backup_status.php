<?php
/**
 * GET /api/backup_status.php - ostatni backup + historia.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/backup_info.php';

json_response([
    'last' => get_last_backup(),
    'history' => get_backup_history(20),
]);

<?php
/**
 * GET /api/processes.php - top 20 procesów wg zużycia CPU.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/process_info.php';

json_response(['processes' => get_top_processes(20)]);

<?php
/**
 * GET /api/cron_list.php - lista zadań cron z harmonogramem, ostatnim wykonaniem i statusem.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/cron_info.php';

json_response(['jobs' => get_cron_jobs()]);

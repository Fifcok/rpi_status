<?php
/**
 * GET /api/services.php - status monitorowanych usług (systemd).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/services_info.php';

json_response(['services' => get_all_services_status()]);

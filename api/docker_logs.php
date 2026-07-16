<?php
/**
 * GET /api/docker_logs.php?container=... - ostatnie logi kontenera.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/docker_info.php';

$container = trim((string) ($_GET['container'] ?? ''));
if ($container === '' || !is_valid_container_ref($container)) {
    json_response(['error' => 'Nieprawidłowy identyfikator kontenera.'], 400);
}

$logs = docker_container_logs($container, 500);

json_response(['container' => $container, 'logs' => $logs]);

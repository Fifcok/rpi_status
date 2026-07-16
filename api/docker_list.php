<?php
/**
 * GET /api/docker_list.php - lista kontenerów Docker wraz z wykorzystaniem CPU/RAM.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/docker_info.php';

json_response([
    'available' => docker_available(),
    'containers' => get_docker_containers(),
]);

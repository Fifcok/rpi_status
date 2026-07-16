<?php
/**
 * GET /api/ssl_list.php - lista certyfikatów SSL/TLS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/ssl_info.php';

json_response(['certificates' => get_ssl_certificates()]);

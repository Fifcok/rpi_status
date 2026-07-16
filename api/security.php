<?php
/**
 * GET /api/security.php - logowania SSH, nieudane próby, ranking IP, firewall, fail2ban.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/security_info.php';

json_response([
    'recent_logins' => get_recent_ssh_logins(20),
    'failed_logins' => array_slice(get_failed_ssh_logins(50), 0, 50),
    'top_attackers' => get_top_attacking_ips(10),
    'firewall' => get_firewall_status(),
    'fail2ban' => get_fail2ban_status(),
]);

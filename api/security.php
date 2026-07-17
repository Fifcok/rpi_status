<?php
/**
 * GET /api/security.php - logowania SSH, nieudane próby, ranking IP, firewall, fail2ban.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/security_info.php';

$summary = get_failed_login_summary(15);

json_response([
    'recent_logins' => get_recent_ssh_logins(15),
    'failed_logins' => get_failed_ssh_logins(15),
    'top_attackers' => $summary['top_ips'],
    'top_usernames' => $summary['top_usernames'],
    'total_attempts' => $summary['total_attempts'],
    'total_unique_ips' => $summary['total_unique_ips'],
    'total_unique_usernames' => $summary['total_unique_usernames'],
    'truncated' => $summary['truncated'],
    'firewall' => get_firewall_status(),
    'fail2ban' => get_fail2ban_status(),
]);

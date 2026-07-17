<?php
/**
 * Wewnętrzny endpoint AJAX zasilający kafelki dashboardu co 5 sekund.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/system_info.php';

$cpu = get_cpu_info();
$ram = get_ram_info();
$disks = get_all_disks_info();
$system = get_system_info();
$network = get_network_info();

json_response([
    'cpu_percent' => $cpu['percent'],
    'cpu_cores' => $cpu['cores'],
    'cpu_temp' => $cpu['temp'],
    'cpu_freq' => $cpu['freq_mhz'],

    'ram_percent' => $ram['percent'],
    'ram_used' => format_bytes($ram['used']),
    'ram_free' => format_bytes($ram['free']),

    'disks' => array_map(static function (array $d): array {
        return [
            'label' => $d['label'],
            'mount' => $d['mount'],
            'percent' => $d['percent'],
            'used' => format_bytes($d['used']),
            'total' => format_bytes($d['total']),
            'free' => format_bytes($d['free']),
        ];
    }, $disks),

    'sys_uptime' => $system['uptime_human'],
    'sys_hostname' => $system['hostname'],
    'sys_lan_ip' => $system['lan_ip'],
    'sys_public_ip' => get_public_ip(),
    'sys_os' => $system['os_version'],
    'sys_kernel' => $system['kernel'],
    'sys_arch' => $system['arch'],

    'net_down' => format_bytes($network['download_bps']) . '/s',
    'net_up' => format_bytes($network['upload_bps']) . '/s',
    'net_connections' => $network['connections'],
    'net_daily_rx' => format_bytes($network['daily_rx']),
    'net_daily_tx' => format_bytes($network['daily_tx']),
]);

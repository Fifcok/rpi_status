<?php
/**
 * REST API: GET /api/status.php
 * Zwraca podstawowe metryki systemowe w formacie JSON: CPU, RAM, DISK, TEMP, UPTIME, NETWORK.
 * Wymaga aktywnej sesji (zalogowania) - patrz includes/api_bootstrap.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/system_info.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'Metoda niedozwolona.'], 405);
}

$cpu = get_cpu_info();
$ram = get_ram_info();
$disk = get_disk_info();
$system = get_system_info();
$network = get_network_info();

json_response([
    'CPU' => [
        'usage_percent' => $cpu['percent'],
        'cores' => $cpu['cores'],
        'frequency_mhz' => $cpu['freq_mhz'],
    ],
    'RAM' => [
        'total_bytes' => $ram['total'],
        'used_bytes' => $ram['used'],
        'free_bytes' => $ram['free'],
        'usage_percent' => $ram['percent'],
    ],
    'DISK' => [
        'total_bytes' => $disk['total'],
        'used_bytes' => $disk['used'],
        'free_bytes' => $disk['free'],
        'usage_percent' => $disk['percent'],
    ],
    'TEMP' => [
        'celsius' => $cpu['temp'],
    ],
    'UPTIME' => [
        'seconds' => $system['uptime_seconds'],
        'human' => $system['uptime_human'],
    ],
    'NETWORK' => [
        'interface' => $network['interface'],
        'download_bps' => $network['download_bps'],
        'upload_bps' => $network['upload_bps'],
        'connections' => $network['connections'],
        'daily_rx_bytes' => $network['daily_rx'],
        'daily_tx_bytes' => $network['daily_tx'],
    ],
    'timestamp' => time(),
]);

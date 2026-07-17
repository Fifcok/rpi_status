#!/usr/bin/env php
<?php
/**
 * Skrypt uruchamiany co minutę z crontaba - zapisuje próbkę metryk (CPU/RAM/dysk/temp)
 * do SQLite oraz sprawdza progi alarmowe (ALERT_THRESHOLDS), status usług, backupu i SSL,
 * zapisując nowe alarmy do tabeli "alerts" (z cooldownem, aby nie spamować tym samym alarmem).
 *
 * Przykładowy wpis crontaba (root lub www-data, jeśli ma dostęp do plików projektu):
 *   * * * * * php /var/www/rpi_status/cron/collect_history.php >> /var/www/rpi_status/logs/cron.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ten skrypt można uruchamiać wyłącznie z linii poleceń.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/system_info.php';
require_once __DIR__ . '/../includes/services_info.php';
require_once __DIR__ . '/../includes/ssl_info.php';
require_once __DIR__ . '/../includes/backup_info.php';

const ALERT_COOLDOWN_SECONDS = 900; // 15 minut - nie powtarzaj tego samego alarmu częściej.

function raise_alert(PDO $pdo, string $type, string $message, string $severity = 'warning'): void
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM alerts
        WHERE type = :type AND acknowledged = 0 AND created_at > :since
    ');
    $stmt->execute([':type' => $type, ':since' => time() - ALERT_COOLDOWN_SECONDS]);
    if ((int) $stmt->fetchColumn() > 0) {
        return; // Alarm tego typu jest już aktywny - nie duplikuj.
    }

    $insert = $pdo->prepare('
        INSERT INTO alerts (created_at, type, message, severity) VALUES (:t, :type, :msg, :sev)
    ');
    $insert->execute([':t' => time(), ':type' => $type, ':msg' => $message, ':sev' => $severity]);
    app_log('warning', "ALARM [{$type}]: {$message}");
}

$pdo = history_db();

$cpu = get_cpu_info('cli');
$ram = get_ram_info();
$disks = get_all_disks_info();

// Do wykresu historii bierzemy partycję root (system) - reszta dysków jest
// nadal sprawdzana pod kątem alarmów poniżej, tylko nie trafia do wykresu.
$rootDisk = null;
foreach ($disks as $d) {
    if ($d['mount'] === '/') {
        $rootDisk = $d;
        break;
    }
}
$rootDiskPercent = $rootDisk['percent'] ?? ($disks[0]['percent'] ?? null);

$insert = $pdo->prepare('
    INSERT INTO metrics_history (recorded_at, cpu_percent, ram_percent, disk_percent, temp_celsius)
    VALUES (:t, :cpu, :ram, :disk, :temp)
');
$insert->execute([
    ':t' => time(),
    ':cpu' => $cpu['percent'],
    ':ram' => $ram['percent'],
    ':disk' => $rootDiskPercent,
    ':temp' => $cpu['temp'],
]);

// --- Progi alarmowe ---
if ($cpu['percent'] !== null && $cpu['percent'] > ALERT_THRESHOLDS['cpu_percent']) {
    raise_alert($pdo, 'cpu_high', "Wysokie użycie CPU: {$cpu['percent']}%", 'warning');
}
if ($ram['percent'] > ALERT_THRESHOLDS['ram_percent']) {
    raise_alert($pdo, 'ram_high', "Wysokie użycie RAM: {$ram['percent']}%", 'warning');
}
foreach ($disks as $d) {
    if ($d['percent'] > ALERT_THRESHOLDS['disk_percent']) {
        $diskKey = preg_replace('/[^a-z0-9]+/i', '_', $d['mount']);
        raise_alert($pdo, 'disk_high_' . $diskKey, "Wysokie zajęcie dysku {$d['label']} ({$d['mount']}): {$d['percent']}%", 'critical');
    }
}
if ($cpu['temp'] !== null && $cpu['temp'] > ALERT_THRESHOLDS['temp_celsius']) {
    raise_alert($pdo, 'temp_high', "Wysoka temperatura CPU: {$cpu['temp']}°C", 'critical');
}

// --- Usługi ---
foreach (get_all_services_status() as $service) {
    if ($service['state'] === 'not-found' || $service['state'] === 'unavailable') {
        continue; // Usługa nieinstalowana - nie jest to awaria.
    }
    if (!$service['active']) {
        raise_alert($pdo, 'service_down_' . $service['unit'], "Usługa {$service['label']} nie działa.", 'critical');
    }
}

// --- SSL ---
foreach (get_ssl_certificates() as $cert) {
    if ($cert['days_left'] <= ALERT_THRESHOLDS['ssl_days_left']) {
        raise_alert(
            $pdo,
            'ssl_expiring_' . $cert['domain'],
            "Certyfikat SSL dla {$cert['domain']} wygasa za {$cert['days_left']} dni.",
            $cert['days_left'] <= 0 ? 'critical' : 'warning'
        );
    }
}

// --- Backup ---
$lastBackup = get_last_backup();
if ($lastBackup !== null && $lastBackup['status'] === 'failed') {
    raise_alert($pdo, 'backup_failed', 'Ostatni backup zakończył się niepowodzeniem: ' . ($lastBackup['message'] ?? ''), 'critical');
}

fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] Zapisano probke metryk (CPU={$cpu['percent']}% RAM={$ram['percent']}% DISK={$rootDiskPercent}% TEMP={$cpu['temp']})\n");

<?php
/**
 * Integracja z Dockerem: lista kontenerów, statystyki, akcje start/stop/restart/logi.
 * Wymaga, aby użytkownik Apache (www-data) był w grupie "docker".
 */

declare(strict_types=1);

function docker_available(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    if (!command_exists('docker')) {
        $available = false;
        return false;
    }
    $ping = safe_exec('docker', ['info', '--format', '{{.ServerVersion}}']);
    $available = $ping !== '';
    return $available;
}

/** Lista kontenerów z podstawowymi informacjami + wykorzystaniem CPU/RAM. */
function get_docker_containers(): array
{
    if (!docker_available()) {
        return [];
    }

    $psOutput = safe_exec('docker', ['ps', '-a', '--format', '{{json .}}']);
    if ($psOutput === '') {
        return [];
    }

    $containers = [];
    foreach (explode("\n", $psOutput) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $data = json_decode($line, true);
        if (!is_array($data)) {
            continue;
        }
        $containers[$data['ID']] = [
            'id' => $data['ID'] ?? '',
            'name' => $data['Names'] ?? '',
            'image' => $data['Image'] ?? '',
            'status_raw' => $data['Status'] ?? '',
            'running' => str_starts_with($data['Status'] ?? '', 'Up'),
            'cpu_percent' => null,
            'ram_usage' => null,
        ];
    }

    // Statystyki (tylko dla działających kontenerów) - jedno wywołanie, bez streamu.
    $statsOutput = safe_exec('docker', ['stats', '--no-stream', '--format', '{{json .}}']);
    foreach (explode("\n", $statsOutput) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $stat = json_decode($line, true);
        if (!is_array($stat)) {
            continue;
        }
        $id = $stat['Container'] ?? '';
        foreach ($containers as $cid => &$container) {
            if (str_starts_with($cid, $id) || str_starts_with($id, $cid)) {
                $container['cpu_percent'] = $stat['CPUPerc'] ?? null;
                $container['ram_usage'] = $stat['MemUsage'] ?? null;
            }
        }
        unset($container);
    }

    return array_values($containers);
}

/** Waliduje ID/nazwę kontenera przed wykonaniem akcji (whitelist znaków). */
function is_valid_container_ref(string $ref): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.\-]{0,127}$/', $ref);
}

/** Wykonuje akcję start/stop/restart na kontenerze. Zwraca [success, message]. */
function docker_container_action(string $containerRef, string $action): array
{
    if (!docker_available()) {
        return [false, 'Docker nie jest dostępny na tym systemie.'];
    }
    if (!is_valid_container_ref($containerRef)) {
        return [false, 'Nieprawidłowy identyfikator kontenera.'];
    }
    if (!in_array($action, ['start', 'stop', 'restart'], true)) {
        return [false, 'Nieznana akcja.'];
    }

    $output = safe_exec('docker', [$action, $containerRef]);
    $ok = trim($output) !== '' || $action === 'stop';

    return [$ok, $ok ? "Wykonano: {$action}" : 'Operacja nie powiodła się.'];
}

/** Zwraca ostatnie N linii logów kontenera. */
function docker_container_logs(string $containerRef, int $lines = 500): string
{
    if (!docker_available() || !is_valid_container_ref($containerRef)) {
        return '';
    }
    $lines = max(1, min($lines, 5000));
    return safe_exec_combined('docker', ['logs', '--tail', (string) $lines, '--timestamps', $containerRef]);
}

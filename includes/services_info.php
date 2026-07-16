<?php
/**
 * Monitoring statusu usług systemd (Apache, Nginx, PHP-FPM, MySQL, MariaDB,
 * Docker, SSH, Redis, Cron). Lista usług pochodzi z whitelisty MONITORED_SERVICES.
 */

declare(strict_types=1);

/** Zwraca status pojedynczej usługi systemd: active / inactive / not-found. */
function get_service_status(string $unit): array
{
    if (!is_safe_identifier($unit)) {
        return ['unit' => $unit, 'state' => 'unknown', 'active' => false];
    }

    if (!command_exists('systemctl')) {
        return ['unit' => $unit, 'state' => 'unavailable', 'active' => false];
    }

    // LoadState="not-found" jednoznacznie oznacza, że jednostka nie jest zainstalowana
    // (w odróżnieniu od "inactive", czyli zainstalowanej, ale zatrzymanej).
    $loadState = safe_exec('systemctl', ['show', $unit, '--property=LoadState', '--value']);
    if ($loadState === 'not-found' || $loadState === '') {
        return ['unit' => $unit, 'state' => 'not-found', 'active' => false];
    }

    $state = safe_exec('systemctl', ['is-active', $unit]);

    return [
        'unit' => $unit,
        'state' => $state ?: 'inactive',
        'active' => $state === 'active',
    ];
}

/** Zwraca status wszystkich monitorowanych usług zdefiniowanych w konfiguracji. */
function get_all_services_status(): array
{
    $results = [];
    foreach (MONITORED_SERVICES as $unit => $label) {
        $status = get_service_status($unit);
        $status['label'] = $label;
        $results[] = $status;
    }
    return $results;
}

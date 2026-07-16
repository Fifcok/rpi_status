<?php
/**
 * Lista zadań cron (crontab użytkownika www-data/root oraz /etc/cron.d).
 * "Ostatnie wykonanie" i "status" są ustalane na podstawie logów systemowych
 * (journalctl/syslog) - jeśli brak wpisów, oznaczane jako "brak danych"
 * zamiast zgadywania.
 */

declare(strict_types=1);

function get_cron_jobs(): array
{
    $jobs = [];

    foreach (['root', 'www-data'] as $user) {
        $raw = safe_exec_privileged('crontab', ['-l', '-u', $user]);
        foreach (parse_crontab($raw, $user) as $job) {
            $jobs[] = $job;
        }
    }

    foreach (glob('/etc/cron.d/*') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $raw = (string) @file_get_contents($file);
        foreach (parse_crontab($raw, basename($file), true) as $job) {
            $jobs[] = $job;
        }
    }

    foreach ($jobs as &$job) {
        $job['last_run'] = find_last_cron_run($job['command']);
        $job['status'] = $job['last_run'] !== null ? 'ok' : 'brak danych';
    }
    unset($job);

    return $jobs;
}

/**
 * Parsuje zawartość crontaba. Format /etc/cron.d różni się od crontab -l
 * dodatkową kolumną z nazwą użytkownika, stąd parametr $isSystemCrontab.
 */
function parse_crontab(string $raw, string $source, bool $isSystemCrontab = false): array
{
    if ($raw === '') {
        return [];
    }

    $jobs = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pattern = $isSystemCrontab
            ? '/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(\S+)\s+(.+)$/'
            : '/^(\S+\s+\S+\s+\S+\s+\S+\s+\S+)\s+(.+)$/';

        if (!preg_match($pattern, $line, $m)) {
            continue;
        }

        $schedule = $m[1];
        $command = $isSystemCrontab ? $m[3] : $m[2];
        $runAsUser = $isSystemCrontab ? $m[2] : $source;

        $jobs[] = [
            'source' => $source,
            'user' => $runAsUser,
            'schedule' => $schedule,
            'command' => $command,
        ];
    }
    return $jobs;
}

/** Szuka w logach systemowych ostatniego wykonania polecenia (dopasowanie po fragmencie komendy). */
function find_last_cron_run(string $command): ?string
{
    $needle = trim(explode(' ', trim($command))[0] ?? '');
    if ($needle === '') {
        return null;
    }

    $log = safe_exec_privileged('journalctl', ['-u', 'cron', '-u', 'crond', '--no-pager', '-n', '500']);
    if ($log === '') {
        $log = safe_exec_privileged('grep', ['CRON', '/var/log/syslog']);
    }
    if ($log === '') {
        return null;
    }

    $lastMatch = null;
    foreach (explode("\n", $log) as $line) {
        if (str_contains($line, $needle)) {
            $lastMatch = $line;
        }
    }

    if ($lastMatch === null) {
        return null;
    }

    if (preg_match('/^(\w{3}\s+\d{1,2}\s+[\d:]+)/', $lastMatch, $m)) {
        $ts = strtotime($m[1]);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    return null;
}

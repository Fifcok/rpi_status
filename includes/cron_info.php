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

    // Lista użytkowników sprawdzana pod kątem własnych crontabów (crontab -l -u <user>).
    // Domyślnie root + www-data; dodaj tu swojego użytkownika SSH (np. w config.php:
    // const CRON_USERS = ['root', 'www-data', 'twoj_user'];), jeśli masz tam własne zadania.
    $users = defined('CRON_USERS') ? CRON_USERS : ['root', 'www-data'];

    foreach ($users as $user) {
        $raw = safe_exec_privileged('crontab', ['-l', '-u', $user]);
        foreach (parse_crontab($raw, 'crontab: ' . $user, $user) as $job) {
            $jobs[] = $job;
        }
    }

    // /etc/crontab - systemowy crontab z dodatkową kolumną użytkownika.
    if (is_readable('/etc/crontab')) {
        $raw = (string) @file_get_contents('/etc/crontab');
        foreach (parse_crontab($raw, '/etc/crontab', null, true) as $job) {
            $jobs[] = $job;
        }
    }

    foreach (glob('/etc/cron.d/*') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $raw = (string) @file_get_contents($file);
        foreach (parse_crontab($raw, basename($file), null, true) as $job) {
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
 * Parsuje zawartość crontaba. Format /etc/crontab i /etc/cron.d różni się od
 * "crontab -l" dodatkową kolumną z nazwą użytkownika, stąd parametr $isSystemCrontab.
 * Dla "crontab -l -u X" użytkownik jest znany z góry ($knownUser) i nie ma go w treści pliku.
 */
function parse_crontab(string $raw, string $source, ?string $knownUser, bool $isSystemCrontab = false): array
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
        $runAsUser = $isSystemCrontab ? $m[2] : ($knownUser ?? '?');

        $jobs[] = [
            'source' => $source,
            'user' => $runAsUser,
            'schedule' => $schedule,
            'command' => $command,
        ];
    }
    return $jobs;
}

/** Pobiera surowy log demona cron (journalctl, z fallbackiem do /var/log/syslog). */
function fetch_cron_raw_log(int $lines = 500): string
{
    $log = safe_exec_privileged('journalctl', ['-u', 'cron', '-u', 'crond', '--no-pager', '-n', (string) $lines]);
    if ($log === '') {
        $log = safe_exec_privileged('grep', ['CRON', '/var/log/syslog']);
    }
    return $log;
}

/** Szuka w logach systemowych ostatniego wykonania polecenia (dopasowanie po fragmencie komendy). */
function find_last_cron_run(string $command): ?string
{
    $needle = trim(explode(' ', trim($command))[0] ?? '');
    if ($needle === '') {
        return null;
    }

    $log = fetch_cron_raw_log(500);
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

/**
 * "Konsola" cron - lista ostatnio faktycznie wykonanych poleceń (nie tylko
 * zdefiniowanych w crontabach), najnowsze pierwsze. Czyta wpisy demona crona
 * z logów systemowych i wyciąga z nich użytkownika oraz polecenie.
 *
 * Każde wykonanie zadania generuje w logu 3 linie: "session opened", samo
 * polecenie ("CMD (...)" lub linia "user: polecenie") i "session closed" -
 * te pierwsze i ostatnie to szum bez treści, więc są tu odfiltrowywane, żeby
 * konsola pokazywała wyłącznie realnie wykonane komendy.
 */
function get_recent_cron_log(int $limit = 15): array
{
    $limit = max(1, min($limit, 200));

    // Więcej surowych linii niż $limit, bo większość to odfiltrowany szum PAM/info.
    $log = fetch_cron_raw_log(1000);
    if ($log === '') {
        return [];
    }

    $entries = [];
    foreach (explode("\n", $log) as $line) {
        $line = trim($line);
        if ($line === '' || stripos($line, 'CRON') === false) {
            continue;
        }
        $entry = parse_cron_log_line($line);
        if ($entry['command'] === null) {
            continue; // pomiń "session opened/closed" i "(CRON) info (No MTA...)"
        }
        $entries[] = $entry;
    }

    // Najnowsze na górze - w logu są chronologicznie od najstarszych.
    $entries = array_reverse($entries);

    return array_slice($entries, 0, $limit);
}

/** Wyciąga znacznik czasu, użytkownika i polecenie z pojedynczej linii logu crona. */
function parse_cron_log_line(string $line): array
{
    $time = null;
    $user = null;
    $command = null;

    if (preg_match('/^(\w{3}\s+\d{1,2}\s+[\d:]+)/', $line, $m)) {
        $ts = strtotime($m[1]);
        $time = $ts ? date('Y-m-d H:i:s', $ts) : null;
    } elseif (preg_match('/^(\d{4}-\d{2}-\d{2}[T ][\d:]+)/', $line, $m)) {
        $ts = strtotime($m[1]);
        $time = $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    if (preg_match('/CRON\[\d+\]:\s*\(([^)]+)\)\s*CMD\s*\((.+)\)\s*$/', $line, $m)) {
        $user = $m[1];
        $command = $m[2];
    }

    return [
        'time' => $time,
        'user' => $user,
        'command' => $command,
        'raw' => $line,
    ];
}

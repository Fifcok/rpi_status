<?php
/**
 * Odczyt ostatnich linii logów (Apache, PHP, System, Cron, Docker, SSH).
 * Źródła zdefiniowane są w whiteliście LOG_SOURCES (config.php) - nigdy nie
 * odczytujemy dowolnej ścieżki podanej przez użytkownika.
 */

declare(strict_types=1);

/** Zwraca ostatnie $limit linii logu dla danego klucza źródła (patrz LOG_SOURCES). */
function read_log_source(string $sourceKey, int $limit = 1000): array
{
    if (!isset(LOG_SOURCES[$sourceKey])) {
        return [];
    }
    $limit = max(1, min($limit, 1000));

    foreach (LOG_SOURCES[$sourceKey]['paths'] as $path) {
        if (str_starts_with($path, 'journalctl:')) {
            $unit = substr($path, strlen('journalctl:'));
            $lines = read_journalctl($unit, $limit);
            if ($lines !== []) {
                return $lines;
            }
            continue;
        }

        if (is_readable($path)) {
            $output = safe_exec('tail', ['-n', (string) $limit, $path]);
            if ($output !== '') {
                return explode("\n", $output);
            }
        }

        // Plik może wymagać podwyższonych uprawnień (np. /var/log/auth.log).
        $output = safe_exec_privileged('tail', ['-n', (string) $limit, $path]);
        if ($output !== '') {
            return explode("\n", $output);
        }
    }

    return [];
}

function read_journalctl(string $unit, int $limit): array
{
    if ($unit === 'system') {
        $output = safe_exec_privileged('journalctl', ['--no-pager', '-n', (string) $limit]);
    } else {
        $output = safe_exec_privileged('journalctl', ['-u', $unit, '--no-pager', '-n', (string) $limit]);
    }
    return $output === '' ? [] : explode("\n", $output);
}

/** Wykrywa poziom logu (INFO/WARNING/ERROR) na podstawie treści linii. */
function detect_log_level(string $line): string
{
    $upper = strtoupper($line);
    if (preg_match('/\b(ERROR|CRIT|CRITICAL|FATAL|EMERG|ALERT)\b/', $upper)) {
        return 'ERROR';
    }
    if (preg_match('/\b(WARN|WARNING)\b/', $upper)) {
        return 'WARNING';
    }
    return 'INFO';
}

/** Filtruje linie logów wg poziomu i frazy wyszukiwania. */
function filter_log_lines(array $lines, ?string $level, ?string $search): array
{
    $result = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        if ($level !== null && $level !== '' && detect_log_level($line) !== $level) {
            continue;
        }
        if ($search !== null && $search !== '' && stripos($line, $search) === false) {
            continue;
        }
        $result[] = ['line' => $line, 'level' => detect_log_level($line)];
    }
    return $result;
}

<?php
/**
 * Funkcje pomocnicze wspólne dla całej aplikacji.
 */

declare(strict_types=1);

/**
 * Bezpiecznie wykonuje polecenie systemowe i zwraca jego wyjście.
 * Jeżeli polecenie nie istnieje lub zwróci błąd, zwraca pusty string
 * zamiast rzucać wyjątkiem — aplikacja ma działać także na systemach
 * bez wszystkich narzędzi (np. brak vcgencmd, smartctl, docker).
 *
 * @param string $command Polecenie bazowe (bez argumentów użytkownika).
 * @param array<int,string> $args Argumenty, każdy zostanie przepuszczony przez escapeshellarg().
 */
function safe_exec(string $command, array $args = []): string
{
    static $available = [];

    $binary = strtok($command, ' ');
    if (!array_key_exists($binary, $available)) {
        $which = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        $available[$binary] = $which !== '';
    }
    if (!$available[$binary]) {
        return '';
    }

    $escapedArgs = array_map('escapeshellarg', $args);
    $full = trim($command . ' ' . implode(' ', $escapedArgs));

    $output = @shell_exec($full . ' 2>/dev/null');
    return $output === null ? '' : trim($output);
}

/**
 * Wariant safe_exec() łączący stdout i stderr (2>&1) - potrzebny np. dla
 * "docker logs", które część danych pisze na stderr.
 */
function safe_exec_combined(string $command, array $args = []): string
{
    $binary = strtok($command, ' ');
    if (!command_exists($binary)) {
        return '';
    }

    $escapedArgs = array_map('escapeshellarg', $args);
    $full = trim($command . ' ' . implode(' ', $escapedArgs));

    $output = @shell_exec($full . ' 2>&1');
    return $output === null ? '' : trim($output);
}

/**
 * Wykonuje polecenie wymagające podwyższonych uprawnień (np. fail2ban-client,
 * lastb, iptables). Najpierw próbuje bez sudo, potem przez "sudo -n" (non-interactive,
 * nigdy nie czeka na hasło - jeśli sudoers nie ma reguły NOPASSWD, po prostu zwróci błąd).
 * Wymaga skonfigurowania /etc/sudoers.d/rpi-status zgodnie z README.
 */
function safe_exec_privileged(string $command, array $args = []): string
{
    $direct = safe_exec($command, $args);
    if ($direct !== '') {
        return $direct;
    }
    if (!command_exists('sudo')) {
        return '';
    }
    $escapedArgs = array_map('escapeshellarg', $args);
    $full = 'sudo -n ' . escapeshellarg($command) . ' ' . implode(' ', $escapedArgs);
    $output = @shell_exec($full . ' 2>/dev/null');
    return $output === null ? '' : trim($output);
}

/** Sprawdza czy dane polecenie jest dostępne w PATH. */
function command_exists(string $binary): bool
{
    $which = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
    return $which !== '';
}

/** Formatuje bajty na czytelną jednostkę (KB, MB, GB, TB). */
function format_bytes(int|float $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $value = $bytes / (1024 ** $pow);
    return round($value, $precision) . ' ' . $units[(int) $pow];
}

/** Formatuje sekundy uptime na format "Xd Xh Xm". */
function format_duration(int $seconds): string
{
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    $parts = [];
    if ($days > 0) $parts[] = $days . 'd';
    if ($hours > 0) $parts[] = $hours . 'h';
    $parts[] = $minutes . 'm';

    return implode(' ', $parts);
}

/** Zwraca odpowiedź JSON i kończy wykonanie skryptu. */
function json_response(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Loguje zdarzenie aplikacyjne do pliku logs/app.log. */
function app_log(string $level, string $message): void
{
    $line = sprintf('[%s] [%s] %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
    @file_put_contents(LOGS_DIR . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

/** Waliduje, że wartość jest bezpiecznym identyfikatorem (litery, cyfry, _ - .). */
function is_safe_identifier(string $value): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_.\-]+$/', $value) && $value !== '' && $value !== '.' && $value !== '..';
}

/** Skraca ciąg znaków do bezpiecznej długości dla wyjścia HTML. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

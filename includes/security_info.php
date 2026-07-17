<?php
/**
 * Sekcja bezpieczeństwa: logowania SSH, nieudane próby, ranking atakujących IP,
 * status firewalla i fail2ban. Wiele z tych poleceń wymaga uprawnień - patrz
 * README.md (konfiguracja /etc/sudoers.d/rpi-status z regułami NOPASSWD).
 */

declare(strict_types=1);

/** Ostatnie udane logowania SSH (polecenie "last"). */
function get_recent_ssh_logins(int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $output = safe_exec_privileged('last', ['-n', (string) $limit, '-F', '-i']);
    if ($output === '') {
        return [];
    }

    $logins = [];
    foreach (explode("\n", trim($output)) as $line) {
        if ($line === '' || str_starts_with($line, 'wtmp begins')) {
            continue;
        }
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 3) {
            continue;
        }
        $logins[] = [
            'user' => $parts[0],
            'ip' => filter_var($parts[2] ?? '', FILTER_VALIDATE_IP) ? $parts[2] : ($parts[2] ?? ''),
            'raw' => $line,
        ];
    }
    return $logins;
}

/** Nieudane próby logowania SSH z "lastb" lub z journalctl jako fallback. */
function get_failed_ssh_logins(int $limit = 50): array
{
    $limit = max(1, min($limit, 500));
    $output = safe_exec_privileged('lastb', ['-n', (string) $limit, '-F', '-i']);

    $failed = [];
    if ($output !== '') {
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '' || str_starts_with($line, 'btmp begins')) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 3) {
                continue;
            }
            $failed[] = ['user' => $parts[0], 'ip' => $parts[2] ?? '', 'raw' => $line];
        }
        return $failed;
    }

    // Fallback: journalctl (sshd) - szukamy "Failed password ... from <ip>"
    $journal = safe_exec_privileged('journalctl', ['-u', 'ssh', '-u', 'sshd', '--no-pager', '-n', '2000']);
    foreach (explode("\n", $journal) as $line) {
        if (preg_match('/Failed password for (?:invalid user )?(\S+) from ([\d.:a-fA-F]+)/', $line, $m)) {
            $failed[] = ['user' => $m[1], 'ip' => $m[2], 'raw' => $line];
            if (count($failed) >= $limit) {
                break;
            }
        }
    }
    return $failed;
}

// Bezpiecznik liczby wpisów wczytywanych przez get_all_failed_ssh_logins() - chroni
// przed eksplozją pamięci, gdyby ktoś wyłączył rotację btmp i plik urósł latami.
// 100000 to duży zapas ponad realistyczny ruch botów w jeden miesiąc (patrz komentarz
// w funkcji) - jeśli suma w UI utyka dokładnie na tej liczbie, to znak, że trzeba
// podnieść wartość jeszcze wyżej, a nie że to prawdziwy limit logów.
const MAX_FAILED_LOGIN_ENTRIES = 100000;

/**
 * Wszystkie nieudane logowania SSH od początku dostępnej historii (bez limitu -n),
 * do liczenia rankingów top-N i sum całkowitych na pełnym zbiorze zamiast tylko
 * ostatnich N prób.
 */
function get_all_failed_ssh_logins(): array
{
    $output = safe_exec_privileged('lastb', ['-F', '-i']);

    $failed = [];
    if ($output !== '') {
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '' || str_starts_with($line, 'btmp begins')) {
                continue;
            }
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 3) {
                continue;
            }
            $failed[] = ['user' => $parts[0], 'ip' => $parts[2] ?? ''];
            if (count($failed) >= MAX_FAILED_LOGIN_ENTRIES) {
                break;
            }
        }
        return $failed;
    }

    // Fallback: journalctl (sshd) - szukamy "Failed password ... from <ip>"
    $journal = safe_exec_privileged('journalctl', ['-u', 'ssh', '-u', 'sshd', '--no-pager', '-n', (string) MAX_FAILED_LOGIN_ENTRIES]);
    foreach (explode("\n", $journal) as $line) {
        if (preg_match('/Failed password for (?:invalid user )?(\S+) from ([\d.:a-fA-F]+)/', $line, $m)) {
            $failed[] = ['user' => $m[1], 'ip' => $m[2]];
            if (count($failed) >= MAX_FAILED_LOGIN_ENTRIES) {
                break;
            }
        }
    }
    return $failed;
}

/**
 * Podsumowanie nieudanych logowań SSH w bieżącym okresie retencji "btmp"
 * (patrz get_all_failed_ssh_logins() - to zwykle "od początku miesiąca", bo
 * logrotate domyślnie rotuje ten plik co miesiąc). Liczy top-N adresów IP
 * i loginów ORAZ sumy całkowite w jednym przebiegu danych, żeby nie odpytywać
 * "lastb" po raz drugi dla samych sum.
 */
function get_failed_login_summary(int $limit = 15): array
{
    $limit = max(1, min($limit, 100));
    $failed = get_all_failed_ssh_logins();

    $ipCounts = [];
    $userCounts = [];
    foreach ($failed as $attempt) {
        $ip = $attempt['ip'];
        if ($ip !== '') {
            $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
        }
        $user = $attempt['user'];
        if ($user !== '' && $user !== '?') {
            $userCounts[$user] = ($userCounts[$user] ?? 0) + 1;
        }
    }

    arsort($ipCounts);
    arsort($userCounts);

    $topIps = [];
    foreach (array_slice($ipCounts, 0, $limit, true) as $ip => $count) {
        $topIps[] = ['ip' => $ip, 'attempts' => $count];
    }

    $topUsernames = [];
    foreach (array_slice($userCounts, 0, $limit, true) as $user => $count) {
        $topUsernames[] = ['user' => $user, 'attempts' => $count];
    }

    return [
        'total_attempts' => count($failed),
        'total_unique_ips' => count($ipCounts),
        'total_unique_usernames' => count($userCounts),
        // Jeśli trafiliśmy dokładnie w bezpiecznik MAX_FAILED_LOGIN_ENTRIES, suma
        // jest ucięta, nie prawdziwa - UI powinno to zasygnalizować zamiast pokazywać
        // okrągłą liczbę jako pewnik.
        'truncated' => count($failed) >= MAX_FAILED_LOGIN_ENTRIES,
        'top_ips' => $topIps,
        'top_usernames' => $topUsernames,
    ];
}

/** Status firewalla: ufw (Debian/Raspberry Pi OS) lub firewalld, z fallbackiem do iptables. */
function get_firewall_status(): array
{
    if (command_exists('ufw')) {
        $out = safe_exec_privileged('ufw', ['status']);
        if ($out !== '') {
            $active = str_contains($out, 'Status: active');
            return ['engine' => 'ufw', 'active' => $active, 'raw' => $out];
        }
    }

    if (command_exists('firewall-cmd')) {
        $out = safe_exec_privileged('firewall-cmd', ['--state']);
        if ($out !== '') {
            return ['engine' => 'firewalld', 'active' => trim($out) === 'running', 'raw' => $out];
        }
    }

    if (command_exists('iptables')) {
        $out = safe_exec_privileged('iptables', ['-L', '-n']);
        if ($out !== '') {
            $ruleCount = substr_count($out, "\n") - 3;
            return ['engine' => 'iptables', 'active' => $ruleCount > 0, 'raw' => $out];
        }
    }

    return ['engine' => 'unknown', 'active' => null, 'raw' => ''];
}

/** Status fail2ban i lista aktywnych jaili. */
function get_fail2ban_status(): array
{
    if (!command_exists('fail2ban-client')) {
        return ['installed' => false, 'active' => false, 'jails' => []];
    }

    $status = safe_exec_privileged('fail2ban-client', ['status']);
    if ($status === '') {
        return ['installed' => true, 'active' => false, 'jails' => []];
    }

    $jails = [];
    if (preg_match('/Jail list:\s*(.*)/', $status, $m)) {
        $jails = array_filter(array_map('trim', explode(',', $m[1])));
    }

    return ['installed' => true, 'active' => true, 'jails' => array_values($jails)];
}

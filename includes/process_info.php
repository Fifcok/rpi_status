<?php
/**
 * Top procesów wg zużycia CPU (polecenie "ps").
 */

declare(strict_types=1);

/** Zwraca N procesów najbardziej obciążających CPU. */
function get_top_processes(int $limit = 20): array
{
    $limit = max(1, min($limit, 200));

    $output = safe_exec('ps', ['-eo', 'pid,comm,%cpu,%mem,etime,rss', '--sort=-%cpu']);
    if ($output === '') {
        return [];
    }

    $lines = explode("\n", trim($output));
    array_shift($lines); // nagłówek

    $processes = [];
    foreach (array_slice($lines, 0, $limit) as $line) {
        $parts = preg_split('/\s+/', trim($line), 6);
        if (count($parts) < 6) {
            continue;
        }
        [$pid, $comm, $cpu, $mem, $etime, $rss] = $parts;
        $processes[] = [
            'pid' => (int) $pid,
            'name' => $comm,
            'cpu_percent' => (float) $cpu,
            'ram_percent' => (float) $mem,
            'ram_bytes' => ((int) $rss) * 1024,
            'elapsed' => $etime,
        ];
    }

    return $processes;
}

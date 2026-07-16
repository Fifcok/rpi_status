<?php
/**
 * GET /api/history.php?range=24h|7d|30d
 * Zwraca historię CPU/RAM/temperatury/dysku dla wykresów Chart.js.
 * Dane zapisywane są co minutę przez cron/collect_history.php.
 * Dla dłuższych zakresów wyniki są agregowane (średnia), aby ograniczyć liczbę punktów.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';

$range = trim((string) ($_GET['range'] ?? '24h'));
$allowedRanges = ['24h', '7d', '30d'];
if (!in_array($range, $allowedRanges, true)) {
    $range = '24h';
}

$now = time();
$ranges = [
    '24h' => ['since' => $now - 86400, 'bucket' => '%Y-%m-%d %H:%M'],
    '7d'  => ['since' => $now - 7 * 86400, 'bucket' => '%Y-%m-%d %H:00'],
    '30d' => ['since' => $now - 30 * 86400, 'bucket' => '%Y-%m-%d'],
];

$since = $ranges[$range]['since'];
$bucketFormat = $ranges[$range]['bucket'];

$pdo = history_db();
$stmt = $pdo->prepare("
    SELECT
        strftime(:fmt, recorded_at, 'unixepoch', 'localtime') AS bucket,
        AVG(cpu_percent) AS cpu_percent,
        AVG(ram_percent) AS ram_percent,
        AVG(disk_percent) AS disk_percent,
        AVG(temp_celsius) AS temp_celsius
    FROM metrics_history
    WHERE recorded_at >= :since
    GROUP BY bucket
    ORDER BY bucket ASC
");
$stmt->execute([':fmt' => $bucketFormat, ':since' => $since]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$labels = [];
$cpu = [];
$ram = [];
$disk = [];
$temp = [];

foreach ($rows as $row) {
    $labels[] = $row['bucket'];
    $cpu[] = $row['cpu_percent'] !== null ? round((float) $row['cpu_percent'], 1) : null;
    $ram[] = $row['ram_percent'] !== null ? round((float) $row['ram_percent'], 1) : null;
    $disk[] = $row['disk_percent'] !== null ? round((float) $row['disk_percent'], 1) : null;
    $temp[] = $row['temp_celsius'] !== null ? round((float) $row['temp_celsius'], 1) : null;
}

json_response([
    'range' => $range,
    'labels' => $labels,
    'cpu' => $cpu,
    'ram' => $ram,
    'disk' => $disk,
    'temp' => $temp,
]);

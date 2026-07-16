<?php
/**
 * GET /api/logs.php?source=apache|php|system|cron|docker|ssh&level=INFO|WARNING|ERROR&search=...
 * Zwraca ostatnie 1000 linii logu z opcjonalnym filtrowaniem.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/logs_reader.php';

$source = trim((string) ($_GET['source'] ?? 'system'));
$level = trim((string) ($_GET['level'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

if (!isset(LOG_SOURCES[$source])) {
    json_response(['error' => 'Nieznane źródło logów.'], 400);
}

$allowedLevels = ['', 'INFO', 'WARNING', 'ERROR'];
if (!in_array($level, $allowedLevels, true)) {
    $level = '';
}

$rawLines = read_log_source($source, 1000);
$filtered = filter_log_lines($rawLines, $level ?: null, $search ?: null);

// Najnowsze na górze.
$filtered = array_reverse($filtered);

json_response([
    'source' => $source,
    'label' => LOG_SOURCES[$source]['label'],
    'total_lines' => count($rawLines),
    'filtered_count' => count($filtered),
    'lines' => array_slice($filtered, 0, 1000),
]);

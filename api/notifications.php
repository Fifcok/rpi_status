<?php
/**
 * GET /api/notifications.php - lista aktywnych (niepotwierdzonych) alarmów.
 * POST /api/notifications.php - potwierdzenie alarmu (parametr: id). Wymaga CSRF.
 * Alarmy generowane są przez cron/collect_history.php na podstawie ALERT_THRESHOLDS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';

$pdo = history_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_response(['error' => 'Nieprawidłowe ID alarmu.'], 400);
    }
    $stmt = $pdo->prepare('UPDATE alerts SET acknowledged = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
    json_response(['success' => true]);
}

$stmt = $pdo->query('
    SELECT id, created_at, type, message, severity
    FROM alerts
    WHERE acknowledged = 0
    ORDER BY created_at DESC
    LIMIT 50
');
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

json_response(['alerts' => $alerts, 'count' => count($alerts)]);

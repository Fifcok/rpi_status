<?php
/**
 * POST /api/docker_action.php - start/stop/restart kontenera. Wymaga tokenu CSRF.
 * Parametry: container (id/nazwa), action (start|stop|restart).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/docker_info.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Metoda niedozwolona.'], 405);
}

csrf_require();

$container = trim((string) ($_POST['container'] ?? ''));
$action = trim((string) ($_POST['action'] ?? ''));

if ($container === '' || $action === '') {
    json_response(['error' => 'Brak wymaganych parametrów.'], 400);
}

[$success, $message] = docker_container_action($container, $action);

app_log('info', sprintf('Akcja Docker "%s" na kontenerze "%s" przez %s: %s', $action, $container, current_user()['username'] ?? '?', $message));

json_response(['success' => $success, 'message' => $message]);

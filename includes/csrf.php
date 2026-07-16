<?php
/**
 * Ochrona przed CSRF dla wszystkich żądań POST (akcje na usługach, Dockerze, backupie).
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    if (!is_string($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/** Do użycia na początku endpointów API obsługujących POST. */
function csrf_require(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify($token)) {
        json_response(['error' => 'Nieprawidłowy token CSRF.'], 403);
    }
}

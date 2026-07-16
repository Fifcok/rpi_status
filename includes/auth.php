<?php
/**
 * Logika uwierzytelniania: logowanie, wylogowanie, ochrona stron i endpointów API.
 */

declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300;

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) ($_SESSION['username'] ?? ''),
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Wymagane na każdej stronie panelu — przekierowuje do logowania jeśli brak sesji. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/** Wymagane na każdym endpoincie API — zwraca 401 JSON zamiast przekierowania. */
function require_login_api(): void
{
    if (!is_logged_in()) {
        json_response(['error' => 'Wymagane zalogowanie.'], 401);
    }
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/** Zwraca true jeśli konto/IP przekroczyło limit prób logowania w ostatnich 5 minutach. */
function is_rate_limited(string $username): bool
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM login_attempts
        WHERE (username = :u OR ip_address = :ip)
          AND success = 0
          AND created_at > :since
    ');
    $stmt->execute([
        ':u' => $username,
        ':ip' => client_ip(),
        ':since' => time() - LOGIN_LOCKOUT_SECONDS,
    ]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function record_login_attempt(string $username, bool $success): void
{
    $pdo = auth_db();
    $stmt = $pdo->prepare('
        INSERT INTO login_attempts (username, ip_address, success, created_at)
        VALUES (:u, :ip, :s, :c)
    ');
    $stmt->execute([
        ':u' => $username,
        ':ip' => client_ip(),
        ':s' => $success ? 1 : 0,
        ':c' => time(),
    ]);
}

/** Próbuje zalogować użytkownika. Zwraca true w razie sukcesu. */
function attempt_login(string $username, string $password): bool
{
    if (is_rate_limited($username)) {
        app_log('warning', "Zablokowano próbę logowania (rate limit) dla '{$username}' z " . client_ip());
        return false;
    }

    $pdo = auth_db();
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ok = $user && password_verify($password, $user['password_hash']);
    record_login_attempt($username, (bool) $ok);

    if (!$ok) {
        app_log('warning', "Nieudane logowanie dla '{$username}' z " . client_ip());
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    $update = $pdo->prepare('UPDATE users SET last_login_at = :t WHERE id = :id');
    $update->execute([':t' => time(), ':id' => $user['id']]);

    app_log('info', "Zalogowano użytkownika '{$username}' z " . client_ip());
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

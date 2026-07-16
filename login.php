<?php
/**
 * Formularz logowania. Ustawia sesję PHP po poprawnej weryfikacji hasła.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Sesja wygasła, odśwież stronę i spróbuj ponownie.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Podaj login i hasło.';
        } elseif (attempt_login($username, $password)) {
            header('Location: ' . BASE_PATH . '/index.php');
            exit;
        } else {
            $error = 'Nieprawidłowy login lub hasło.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/style.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center">
<div class="login-card card">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-cpu-fill text-accent" style="font-size:2.5rem;"></i>
            <h4 class="mt-2 mb-0"><?= h(APP_NAME) ?></h4>
            <div class="text-muted small">Zaloguj się, aby kontynuować</div>
        </div>

        <?php if ($error !== null): ?>
        <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label small">Login</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small">Hasło</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-accent w-100">Zaloguj się</button>
        </form>
    </div>
</div>
</body>
</html>

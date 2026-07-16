<?php
/**
 * Wspólny nagłówek HTML dla wszystkich stron panelu.
 * Oczekuje zmiennych: $pageTitle (string), $activePage (string, klucz z sidebar.php).
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<meta name="base-path" content="<?= h(BASE_PATH) ?>">
<title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= h(BASE_PATH) ?>/assets/css/style.css">
</head>
<body>
<nav class="topbar d-flex align-items-center justify-content-between px-3">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-icon d-lg-none" id="sidebarToggle" aria-label="Menu" type="button">
            <i class="bi bi-list fs-4"></i>
        </button>
        <i class="bi bi-cpu-fill text-accent fs-4"></i>
        <span class="fw-semibold"><?= h(APP_NAME) ?></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-icon position-relative" id="alertBell" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                <i class="bi bi-bell fs-5"></i>
                <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle d-none" id="alertCount">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 alert-dropdown" id="alertDropdown">
                <div class="text-muted small px-2 py-3 text-center">Brak aktywnych alarmów</div>
            </div>
        </div>
        <?php if ($user): ?>
        <div class="dropdown">
            <button class="btn btn-icon d-flex align-items-center gap-1" data-bs-toggle="dropdown" type="button">
                <i class="bi bi-person-circle fs-5"></i>
                <span class="d-none d-md-inline"><?= h($user['username']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= h(BASE_PATH) ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Wyloguj</a></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>

<div class="app-shell">
    <?php require APP_ROOT . '/includes/sidebar.php'; ?>
    <main class="app-main">

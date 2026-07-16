<?php
/**
 * Wspólny bootstrap dla wszystkich endpointów api/*.php:
 * ładuje konfigurację, wymaga zalogowanej sesji i ustawia nagłówki JSON.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_login_api();

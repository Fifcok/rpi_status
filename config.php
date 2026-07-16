<?php
/**
 * Globalna konfiguracja aplikacji.
 * Musi być dołączona jako pierwszy plik w każdym punkcie wejścia.
 */

declare(strict_types=1);

// Błędy logujemy do pliku, nigdy nie wypisujemy na ekran (produkcja).
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('APP_ROOT', __DIR__);
define('APP_NAME', 'RPi5 Admin Dashboard');

ini_set('error_log', APP_ROOT . '/logs/php_errors.log');

// --- Konfiguracja sesji (przed session_start) ---
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
if (!empty($_SERVER['HTTPS'])) {
    ini_set('session.cookie_secure', '1');
}
session_name('rpi_status_sid');

// Skrypty CLI (cron/collect_history.php) nie potrzebują sesji HTTP.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Strefa czasowa
date_default_timezone_set('Europe/Warsaw');

// --- Ścieżki ---
define('DATA_DIR', APP_ROOT . '/data');
define('LOGS_DIR', APP_ROOT . '/logs');
define('BACKUP_DIR', APP_ROOT . '/backup');
define('HISTORY_DB', DATA_DIR . '/history.sqlite');
define('AUTH_DB', DATA_DIR . '/auth.sqlite');

// --- Progi alarmowe ---
const ALERT_THRESHOLDS = [
    'cpu_percent'   => 90,
    'ram_percent'   => 90,
    'disk_percent'  => 90,
    'temp_celsius'  => 80,
    'ssl_days_left' => 14,
];

// --- Katalogi logów systemowych dostępnych z poziomu panelu (whitelist) ---
const LOG_SOURCES = [
    'apache'  => ['label' => 'Apache',  'paths' => ['/var/log/apache2/error.log', '/var/log/httpd/error_log']],
    'php'     => ['label' => 'PHP',     'paths' => ['/var/log/php_errors.log', '/var/log/php8.3-fpm.log']],
    'system'  => ['label' => 'System',  'paths' => ['journalctl:system']],
    'cron'    => ['label' => 'Cron',    'paths' => ['journalctl:cron', '/var/log/cron.log']],
    'docker'  => ['label' => 'Docker',  'paths' => ['journalctl:docker']],
    'ssh'     => ['label' => 'SSH',     'paths' => ['journalctl:ssh', '/var/log/auth.log']],
];

// --- Usługi monitorowane na kafelku "Monitoring usług" (whitelist nazw jednostek systemd) ---
const MONITORED_SERVICES = [
    'apache2'   => 'Apache',
    'nginx'     => 'Nginx',
    'php8.3-fpm'=> 'PHP-FPM',
    'mysql'     => 'MySQL',
    'mariadb'   => 'MariaDB',
    'docker'    => 'Docker',
    'ssh'       => 'SSH',
    'redis-server' => 'Redis',
    'cron'      => 'Cron',
];

// --- Backup ---
const BACKUP_TARGETS = [
    'www'    => '/var/www',
    'config' => '/etc',
];

// Dane logowania do MySQL/MariaDB używane wyłącznie do odczytu metadanych i mysqldump.
// Zalecane: konto z uprawnieniami tylko SELECT / PROCESS / LOCK TABLES.
const DB_MYSQL_HOST = '127.0.0.1';
const DB_MYSQL_USER = 'rpi_status_ro';
const DB_MYSQL_PASS = '';
const DB_MYSQL_PORT = 3306;

require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/csrf.php';

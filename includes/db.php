<?php
/**
 * Warstwa dostępu do baz SQLite (historia metryk + użytkownicy).
 * Używa PDO z parametrami przygotowanymi wszędzie, gdzie wstawiane są dane.
 */

declare(strict_types=1);

/** Zwraca (i tworzy przy pierwszym użyciu) połączenie PDO do bazy historii metryk. */
function history_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(HISTORY_DB);
    $pdo = new PDO('sqlite:' . HISTORY_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        init_history_schema($pdo);
    } else {
        // Upewnij się, że tabele istnieją nawet jeśli plik był pusty/uszkodzony.
        init_history_schema($pdo);
    }

    return $pdo;
}

function init_history_schema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS metrics_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recorded_at INTEGER NOT NULL,
            cpu_percent REAL,
            ram_percent REAL,
            disk_percent REAL,
            temp_celsius REAL
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_metrics_recorded_at ON metrics_history (recorded_at)');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at INTEGER NOT NULL,
            type TEXT NOT NULL,
            message TEXT NOT NULL,
            severity TEXT NOT NULL DEFAULT "warning",
            acknowledged INTEGER NOT NULL DEFAULT 0
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_alerts_created_at ON alerts (created_at)');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS backup_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            started_at INTEGER NOT NULL,
            finished_at INTEGER,
            status TEXT NOT NULL DEFAULT "running",
            size_bytes INTEGER DEFAULT 0,
            file_path TEXT,
            message TEXT
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS network_daily (
            day TEXT PRIMARY KEY,
            rx_bytes INTEGER NOT NULL DEFAULT 0,
            tx_bytes INTEGER NOT NULL DEFAULT 0
        )
    ');
}

/** Zwraca (i tworzy przy pierwszym użyciu) połączenie PDO do bazy użytkowników/sesji. */
function auth_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $isNew = !file_exists(AUTH_DB);
    $pdo = new PDO('sqlite:' . AUTH_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($isNew) {
        init_auth_schema($pdo);
    } else {
        init_auth_schema($pdo);
    }

    return $pdo;
}

function init_auth_schema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_login_at INTEGER
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            success INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )
    ');

    // Domyślny użytkownik tworzony tylko przy pierwszej inicjalizacji bazy.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (:u, :p, :c)');
        $stmt->execute([
            ':u' => 'admin',
            ':p' => password_hash('admin', PASSWORD_DEFAULT),
            ':c' => time(),
        ]);
        app_log('info', 'Utworzono domyślnego użytkownika admin/admin - ZMIEŃ HASŁO NATYCHMIAST.');
    }
}

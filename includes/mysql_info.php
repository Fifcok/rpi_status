<?php
/**
 * Odczyt metadanych MySQL/MariaDB: lista baz, rozmiar, liczba tabel, największe tabele.
 * Używa wyłącznie zapytań do information_schema z parametrami przygotowanymi.
 */

declare(strict_types=1);

function mysql_available(): bool
{
    return DB_MYSQL_USER !== '' && (command_exists('mysql') || extension_loaded('pdo_mysql'));
}

function mysql_connect_ro(): ?PDO
{
    if (!extension_loaded('pdo_mysql')) {
        return null;
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_MYSQL_HOST, DB_MYSQL_PORT);
        return new PDO($dsn, DB_MYSQL_USER, DB_MYSQL_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (PDOException $e) {
        app_log('warning', 'Brak połączenia z MySQL: ' . $e->getMessage());
        return null;
    }
}

/** Zwraca listę baz danych z rozmiarem i liczbą tabel. */
function get_mysql_databases(): array
{
    $pdo = mysql_connect_ro();
    if ($pdo === null) {
        return [];
    }

    $stmt = $pdo->query("
        SELECT table_schema AS db_name,
               COUNT(*) AS table_count,
               SUM(data_length + index_length) AS size_bytes
        FROM information_schema.tables
        WHERE table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
        GROUP BY table_schema
        ORDER BY size_bytes DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Zwraca N największych tabel w danej bazie (nazwa bazy walidowana przez whitelist z get_mysql_databases). */
function get_mysql_largest_tables(string $database, int $limit = 10): array
{
    $pdo = mysql_connect_ro();
    if ($pdo === null || !is_safe_identifier($database)) {
        return [];
    }

    $stmt = $pdo->prepare('
        SELECT table_name, table_rows, (data_length + index_length) AS size_bytes
        FROM information_schema.tables
        WHERE table_schema = :db
        ORDER BY size_bytes DESC
        LIMIT :lim
    ');
    $stmt->bindValue(':db', $database, PDO::PARAM_STR);
    $stmt->bindValue(':lim', max(1, min($limit, 100)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

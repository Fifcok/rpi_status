<?php
/**
 * Logika backupu: www (BACKUP_TARGETS['www']), bazy danych MySQL/MariaDB
 * (mysqldump per baza) oraz konfiguracja (BACKUP_TARGETS['config']).
 * Wynik jest archiwum tar.gz w katalogu backup/ oraz wpis w tabeli backup_history.
 */

declare(strict_types=1);

/** Zwraca informacje o ostatnim backupie (data, rozmiar, status). */
function get_last_backup(): ?array
{
    $pdo = history_db();
    $stmt = $pdo->query('SELECT * FROM backup_history ORDER BY started_at DESC LIMIT 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Historia backupów (ostatnie N). */
function get_backup_history(int $limit = 20): array
{
    $pdo = history_db();
    $stmt = $pdo->prepare('SELECT * FROM backup_history ORDER BY started_at DESC LIMIT :lim');
    $stmt->bindValue(':lim', max(1, min($limit, 200)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Wykonuje pełny backup (www + bazy danych + konfiguracja) i zapisuje wynik
 * jako pojedyncze archiwum tar.gz w katalogu backup/.
 * Zwraca tablicę z polami: success, message, file, size_bytes.
 */
function run_full_backup(): array
{
    $pdo = history_db();
    $startedAt = time();

    $insert = $pdo->prepare('INSERT INTO backup_history (started_at, status) VALUES (:t, "running")');
    $insert->execute([':t' => $startedAt]);
    $backupId = (int) $pdo->lastInsertId();

    $timestamp = date('Ymd_His', $startedAt);
    $workDir = BACKUP_DIR . '/tmp_' . $timestamp;
    $archivePath = BACKUP_DIR . '/backup_' . $timestamp . '.tar.gz';

    try {
        if (!mkdir($workDir, 0750, true) && !is_dir($workDir)) {
            throw new RuntimeException('Nie można utworzyć katalogu roboczego backupu.');
        }

        // 1) Zrzut baz danych MySQL/MariaDB (jeśli dostępne narzędzia).
        $dbDir = $workDir . '/databases';
        mkdir($dbDir, 0750, true);
        if (command_exists('mysqldump')) {
            foreach (get_mysql_databases() as $db) {
                $name = $db['db_name'];
                if (!is_safe_identifier($name)) {
                    continue;
                }
                $outFile = $dbDir . '/' . $name . '.sql';
                // Hasło przekazywane przez zmienną środowiskową MYSQL_PWD zamiast --password,
                // aby nie było widoczne w liście procesów (ps aux).
                $cmd = (DB_MYSQL_PASS !== '' ? 'MYSQL_PWD=' . escapeshellarg(DB_MYSQL_PASS) . ' ' : '')
                    . 'mysqldump --host=' . escapeshellarg(DB_MYSQL_HOST)
                    . ' --port=' . escapeshellarg((string) DB_MYSQL_PORT)
                    . ' --user=' . escapeshellarg(DB_MYSQL_USER)
                    . ' --single-transaction --quick ' . escapeshellarg($name)
                    . ' > ' . escapeshellarg($outFile) . ' 2>/dev/null';
                @shell_exec($cmd);
            }
        }

        // 2) Kopiowanie plików www i konfiguracji (rsync jeśli dostępny, inaczej cp -r).
        //
        // WAŻNE: katalog backupu tej aplikacji (backup/) leży fizycznie wewnątrz
        // BACKUP_TARGETS['www'] (/var/www), razem z data/ i logs/. Bez wykluczenia
        // każdy kolejny backup kopiowałby WSZYSTKIE poprzednie archiwa (i właśnie
        // tworzony, rosnący katalog roboczy) do samego siebie - w praktyce
        // samonapędzająca się, nigdy niekończąca się kopia zżerająca całe CPU/dysk.
        foreach (BACKUP_TARGETS as $key => $sourcePath) {
            if (!is_readable($sourcePath)) {
                continue;
            }
            $destPath = $workDir . '/' . $key;
            mkdir($destPath, 0750, true);
            $source = rtrim($sourcePath, '/');

            $excludeAbsolute = [];
            foreach ([DATA_DIR, LOGS_DIR, BACKUP_DIR, APP_ROOT . '/.git'] as $ownDir) {
                if (path_is_within($ownDir, $source)) {
                    $excludeAbsolute[] = $ownDir;
                }
            }

            if (command_exists('rsync')) {
                $args = ['-a'];
                foreach ($excludeAbsolute as $excluded) {
                    $args[] = '--exclude=' . relative_path($source, $excluded);
                }
                $args[] = $source . '/';
                $args[] = $destPath . '/';
                safe_exec('rsync', $args);
            } else {
                copy_directory_excluding($source, $destPath, $excludeAbsolute);
            }
        }

        // 3) Kompresja całości do jednego archiwum.
        $tarResult = safe_exec('tar', ['-czf', $archivePath, '-C', $workDir, '.']);

        remove_directory_recursive($workDir);

        if (!file_exists($archivePath)) {
            throw new RuntimeException('Archiwum backupu nie zostało utworzone (brak narzędzia tar?).');
        }

        $size = filesize($archivePath) ?: 0;

        $update = $pdo->prepare('
            UPDATE backup_history
            SET finished_at = :f, status = "success", size_bytes = :s, file_path = :p, message = "OK"
            WHERE id = :id
        ');
        $update->execute([':f' => time(), ':s' => $size, ':p' => $archivePath, ':id' => $backupId]);

        return ['success' => true, 'message' => 'Backup zakończony pomyślnie.', 'file' => basename($archivePath), 'size_bytes' => $size];
    } catch (Throwable $e) {
        if (is_dir($workDir)) {
            remove_directory_recursive($workDir);
        }
        $update = $pdo->prepare('
            UPDATE backup_history SET finished_at = :f, status = "failed", message = :m WHERE id = :id
        ');
        $update->execute([':f' => time(), ':m' => $e->getMessage(), ':id' => $backupId]);

        app_log('error', 'Backup nieudany: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage(), 'file' => null, 'size_bytes' => 0];
    }
}

/** Czy $child leży wewnątrz (lub jest równy) katalogowi $parent - porównanie na realpath(). */
function path_is_within(string $child, string $parent): bool
{
    $childReal = realpath($child);
    $parentReal = realpath($parent);
    if ($childReal === false || $parentReal === false) {
        return false;
    }
    $parentReal = rtrim($parentReal, '/');
    return $childReal === $parentReal || str_starts_with($childReal, $parentReal . '/');
}

/** Ścieżka $path względem $base (do użycia w rsync --exclude, który jest relatywny do źródła). */
function relative_path(string $base, string $path): string
{
    $baseReal = rtrim((string) realpath($base), '/');
    $pathReal = (string) realpath($path);
    return ltrim(substr($pathReal, strlen($baseReal)), '/');
}

/**
 * Fallback dla systemów bez rsync: rekurencyjne kopiowanie z pominięciem
 * wskazanych katalogów bezwzględnych (np. własnego backup/, żeby uniknąć
 * kopiowania katalogu backupu do samego siebie w nieskończoność).
 */
function copy_directory_excluding(string $src, string $dest, array $excludeAbsolute): void
{
    if (!is_dir($dest) && !mkdir($dest, 0750, true) && !is_dir($dest)) {
        return;
    }

    $entries = @scandir($src);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $srcPath = $src . '/' . $entry;
        foreach ($excludeAbsolute as $excluded) {
            if (path_is_within($srcPath, $excluded)) {
                continue 2;
            }
        }

        $destPath = $dest . '/' . $entry;
        if (is_dir($srcPath) && !is_link($srcPath)) {
            copy_directory_excluding($srcPath, $destPath, $excludeAbsolute);
        } elseif (is_file($srcPath)) {
            @copy($srcPath, $destPath);
        }
    }
}

function remove_directory_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

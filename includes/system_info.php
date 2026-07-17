<?php
/**
 * Zbieranie danych systemowych z Raspberry Pi (CPU, RAM, dysk, system, sieć).
 * Każda funkcja jest odporna na brak danego polecenia w systemie — zwraca
 * wtedy wartości domyślne/null zamiast wywalać błąd.
 */

declare(strict_types=1);

/**
 * CPU: użycie %, liczba rdzeni, temperatura, taktowanie.
 *
 * @param string $context Rozdziela punkt odniesienia do pomiaru CPU (patrz
 *   cpu_usage_percent()) między różne pętle odpytywania - domyślnie 'web'
 *   (dashboard AJAX co 5s), cron/collect_history.php woła z 'cli' (co 60s),
 *   żeby te dwa niezależne cykle nie nadpisywały sobie nawzajem punktu odniesienia.
 */
function get_cpu_info(string $context = 'web'): array
{
    $cores = (int) (safe_exec('nproc') ?: shell_exec('nproc') ?: 1);
    if ($cores < 1) {
        $cores = (int) substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');
        $cores = max($cores, 1);
    }

    $percent = cpu_usage_percent($context);

    // Temperatura: najpierw vcgencmd (specyficzne dla RPi), potem thermal_zone.
    $temp = null;
    $vc = safe_exec('vcgencmd', ['measure_temp']);
    if ($vc !== '' && preg_match('/temp=([\d.]+)/', $vc, $m)) {
        $temp = (float) $m[1];
    } elseif (is_readable('/sys/class/thermal/thermal_zone0/temp')) {
        $raw = trim((string) @file_get_contents('/sys/class/thermal/thermal_zone0/temp'));
        if (is_numeric($raw)) {
            $temp = round(((float) $raw) / 1000, 1);
        }
    }

    // Taktowanie: vcgencmd measure_clock arm (Hz) lub /proc/cpuinfo (MHz).
    $freqMhz = null;
    $clock = safe_exec('vcgencmd', ['measure_clock', 'arm']);
    if ($clock !== '' && preg_match('/=(\d+)/', $clock, $m)) {
        $freqMhz = round(((int) $m[1]) / 1_000_000, 0);
    } else {
        $cpuinfo = (string) @file_get_contents('/proc/cpuinfo');
        if (preg_match('/cpu MHz\s*:\s*([\d.]+)/', $cpuinfo, $m)) {
            $freqMhz = round((float) $m[1], 0);
        }
    }

    return [
        'percent'   => $percent,
        'cores'     => $cores,
        'temp'      => $temp,
        'freq_mhz'  => $freqMhz,
    ];
}

/** Odczytuje surowe liczniki jiffies z pierwszej linii /proc/stat ("cpu ..."). */
function read_proc_stat_cpu(): ?array
{
    $line = @file_get_contents('/proc/stat');
    if ($line === false) {
        return null;
    }
    $firstLine = strtok($line, "\n");
    $parts = preg_split('/\s+/', trim($firstLine));
    array_shift($parts); // "cpu"
    return array_map('intval', $parts);
}

/** Liczy % zużycia CPU na podstawie różnicy dwóch odczytów /proc/stat. */
function calculate_cpu_percent(array $previous, array $current): ?float
{
    $idle1 = ($previous[3] ?? 0) + ($previous[4] ?? 0);
    $idle2 = ($current[3] ?? 0) + ($current[4] ?? 0);
    $total1 = array_sum($previous);
    $total2 = array_sum($current);

    $totalDelta = $total2 - $total1;
    $idleDelta = $idle2 - $idle1;

    if ($totalDelta <= 0) {
        return null;
    }
    return round((1 - $idleDelta / $totalDelta) * 100, 1);
}

/**
 * Zużycie CPU % - liczone jako średnia od POPRZEDNIEGO wywołania (punkt
 * odniesienia trzymany w pliku), a nie z krótkiej 200ms próbki w trakcie
 * jednego żądania. Krótkie próbki są zaszumione: na w miarę pustym systemie
 * łapią zero, a gdy trafią na start kilku procesów (np. kilka zadań cron
 * odpalających się w tej samej sekundzie) - sztucznie wysoki wynik, bo ten
 * krótki wybuch dominuje w tak małym oknie pomiaru.
 *
 * $context rozdziela punkt odniesienia dla różnych pętli odpytujących (web
 * dashboard co 5s vs cron co 60s), żeby nie nadpisywały sobie nawzajem bazy
 * porównania i każda dostawała średnią z odpowiadającego jej okresu.
 */
function cpu_usage_percent(string $context = 'web'): ?float
{
    $current = read_proc_stat_cpu();
    if ($current === null) {
        return null;
    }

    $cacheFile = DATA_DIR . '/cpu_baseline_' . preg_replace('/[^a-z0-9_]/', '', $context) . '.json';

    $previous = null;
    if (is_readable($cacheFile)) {
        $decoded = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($decoded)) {
            $previous = $decoded;
        }
    }

    @file_put_contents($cacheFile, json_encode($current), LOCK_EX);

    if ($previous === null) {
        // Pierwsze wywołanie w tym kontekście - brak punktu odniesienia.
        // Zwróć jednorazową próbkę 200ms, żeby od razu pokazać sensowną wartość.
        usleep(200000);
        $second = read_proc_stat_cpu();
        return $second !== null ? calculate_cpu_percent($current, $second) : null;
    }

    return calculate_cpu_percent($previous, $current);
}

/** RAM: zajęta, wolna, procent, total (bajty). */
function get_ram_info(): array
{
    $meminfo = @file_get_contents('/proc/meminfo');
    if ($meminfo === false) {
        return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
    }

    $values = [];
    foreach (['MemTotal', 'MemAvailable', 'MemFree', 'Buffers', 'Cached'] as $key) {
        if (preg_match('/^' . $key . ':\s+(\d+)\s*kB/m', $meminfo, $m)) {
            $values[$key] = ((int) $m[1]) * 1024;
        } else {
            $values[$key] = 0;
        }
    }

    $total = $values['MemTotal'];
    $available = $values['MemAvailable'] ?: ($values['MemFree'] + $values['Buffers'] + $values['Cached']);
    $used = max($total - $available, 0);
    $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

    return [
        'total' => $total,
        'used' => $used,
        'free' => $available,
        'percent' => $percent,
    ];
}

/**
 * Dysk: zajętość, wolne miejsce, procent (dla partycji root).
 * Wartości trzymane są jako float, NIE int: na 32-bitowym PHP (np. Raspberry Pi OS
 * w wersji 32-bitowej - "armhf") rzutowanie (int) na rozmiar dysku większy niż ~2GB
 * (limit 32-bitowego int, PHP_INT_MAX = 2147483647) obcina wartość, przez co duże
 * dyski pokazywały się jako "2 GB". Float (double) w PHP bezpiecznie mieści
 * precyzyjnie liczby całkowite aż do ~9*10^15 (petabajty), więc problem nie występuje.
 */
function get_disk_info(string $mount = '/'): array
{
    $total = @disk_total_space($mount);
    $free = @disk_free_space($mount);

    if ($total === false || $free === false || $total === 0) {
        return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
    }

    $used = $total - $free;
    $percent = round(($used / $total) * 100, 1);

    return [
        'total' => (float) $total,
        'used' => (float) $used,
        'free' => (float) $free,
        'percent' => $percent,
    ];
}

/** Lista dysków/partycji przez lsblk (opcjonalnie, dla widoku szczegółowego). */
function get_block_devices(): array
{
    $json = safe_exec('lsblk', ['-J', '-b', '-o', 'NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE']);
    if ($json === '') {
        return [];
    }
    $data = json_decode($json, true);
    return $data['blockdevices'] ?? [];
}

/**
 * Zajętość WSZYSTKICH zamontowanych dysków fizycznych (np. karta SD z systemem +
 * dysk zewnętrzny), a nie tylko partycji root.
 *
 * Liczby total/used/free pochodzą bezpośrednio z polecenia "df" (a nie z PHP-owych
 * disk_total_space()/disk_free_space()) celowo: te funkcje PHP opierają się na
 * statvfs() i potrafią zwracać błędne, niespójne wartości na dyskach zewnętrznych
 * zamontowanych przez FUSE (exfat-fuse, ntfs-3g) - typowy przypadek dla dysku USB
 * podłączonego do Raspberry Pi. "df" pyta jądro bezpośrednio i jest wiarygodne
 * niezależnie od systemu plików.
 */
function get_all_disks_info(): array
{
    $disks = get_disks_via_df();
    if ($disks !== []) {
        return $disks;
    }

    // Fallback: "df" niedostępne - spróbuj przez lsblk + funkcje PHP.
    $disks = get_disks_via_lsblk();
    if ($disks !== []) {
        return $disks;
    }

    // Ostateczny fallback: przynajmniej partycja root.
    $root = get_disk_info('/');
    if ($root['total'] > 0) {
        return [array_merge(['device' => '/', 'mount' => '/', 'label' => 'System (SD)'], $root)];
    }

    return [];
}

/** Główne źródło danych o dyskach: "df" z wykluczeniem systemów plików wirtualnych/pseudo. */
function get_disks_via_df(): array
{
    $excludedTypes = [
        'tmpfs', 'devtmpfs', 'proc', 'sysfs', 'cgroup', 'cgroup2', 'overlay',
        'squashfs', 'devpts', 'securityfs', 'pstore', 'debugfs', 'mqueue',
        'tracefs', 'configfs', 'autofs', 'binfmt_misc', 'fusectl', 'rpc_pipefs', 'efivarfs',
    ];

    // Uwaga: -P i --output wzajemnie się wykluczają w GNU df - --output daje pełną
    // kontrolę nad kolumnami, więc -P (format POSIX) jest tu zbędne.
    $args = ['-B1', '--output=source,target,fstype,size,used,avail,pcent'];
    foreach ($excludedTypes as $type) {
        $args[] = '-x';
        $args[] = $type;
    }

    $output = safe_exec('df', $args);
    if ($output === '') {
        return [];
    }

    $lines = explode("\n", trim($output));
    array_shift($lines); // nagłówek kolumn

    $disks = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        // source target fstype size used avail pcent% - target może zawierać spacje
        // (np. automatycznie zamontowane dyski USB pod /media/user/Nazwa Dysku).
        if (!preg_match('/^(\S+)\s+(.+?)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%$/', $line, $m)) {
            continue;
        }
        [, $source, $target, , $size, $used, $avail] = $m;
        // Float, nie int - patrz komentarz przy get_disk_info() (obcinanie na 32-bit PHP).
        $size = (float) $size;
        $used = (float) $used;
        $avail = (float) $avail;
        if ($size <= 0) {
            continue;
        }

        $disks[] = [
            'device' => $source,
            'mount' => $target,
            'label' => $target === '/' ? 'System (SD)' : basename($target),
            'total' => $size,
            'used' => $used,
            'free' => $avail,
            'percent' => round(($used / $size) * 100, 1),
        ];
    }

    return $disks;
}

/** Fallback, gdy "df" nie jest dostępne: lsblk + PHP disk_total_space()/disk_free_space(). */
function get_disks_via_lsblk(): array
{
    $disks = [];
    $seenMounts = [];

    $collect = static function (array $dev) use (&$collect, &$disks, &$seenMounts): void {
        $mount = $dev['mountpoint'] ?? null;
        $type = $dev['type'] ?? '';

        if ($mount !== null && $mount !== '' && $type !== 'loop' && !isset($seenMounts[$mount])) {
            $total = @disk_total_space($mount);
            $free = @disk_free_space($mount);

            if ($total !== false && $free !== false && $total > 0) {
                $used = $total - $free;
                $disks[] = [
                    'device' => '/dev/' . ($dev['name'] ?? '?'),
                    'mount' => $mount,
                    'label' => $mount === '/' ? 'System (SD)' : basename($mount),
                    // Float, nie int - patrz komentarz przy get_disk_info() (obcinanie na 32-bit PHP).
                    'total' => (float) $total,
                    'used' => (float) $used,
                    'free' => (float) $free,
                    'percent' => round(($used / $total) * 100, 1),
                ];
                $seenMounts[$mount] = true;
            }
        }

        foreach ($dev['children'] ?? [] as $child) {
            $collect($child);
        }
    };

    foreach (get_block_devices() as $device) {
        $collect($device);
    }

    return $disks;
}

/** Informacje systemowe: uptime, hostname, IP LAN, wersja OS, kernel, architektura. */
function get_system_info(): array
{
    $uptimeSeconds = 0;
    $uptimeRaw = @file_get_contents('/proc/uptime');
    if ($uptimeRaw !== false) {
        $uptimeSeconds = (int) floatval(strtok($uptimeRaw, ' '));
    }

    $hostname = safe_exec('hostname') ?: (string) gethostname();

    $lanIp = get_lan_ip();

    $osVersion = 'Nieznana';
    if (is_readable('/etc/os-release')) {
        $osRelease = (string) @file_get_contents('/etc/os-release');
        if (preg_match('/PRETTY_NAME="([^"]+)"/', $osRelease, $m)) {
            $osVersion = $m[1];
        }
    }

    $kernel = safe_exec('uname', ['-r']) ?: php_uname('r');
    $arch = safe_exec('uname', ['-m']) ?: php_uname('m');

    return [
        'hostname' => $hostname,
        'uptime_seconds' => $uptimeSeconds,
        'uptime_human' => format_duration($uptimeSeconds),
        'lan_ip' => $lanIp,
        'os_version' => $osVersion,
        'kernel' => $kernel,
        'arch' => $arch,
    ];
}

function get_lan_ip(): string
{
    $out = safe_exec('hostname', ['-I']);
    if ($out !== '') {
        $ips = preg_split('/\s+/', trim($out));
        return $ips[0] ?? '';
    }

    // Fallback: ip route
    $route = safe_exec('ip', ['route', 'get', '1']);
    if ($route !== '' && preg_match('/src\s+(\S+)/', $route, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Adres publiczny - pobierany asynchronicznie/rzadko i cache'owany do pliku,
 * aby nie odpytywać zewnętrznego serwisu co 5 sekund.
 */
function get_public_ip(): string
{
    $cacheFile = DATA_DIR . '/public_ip.cache';
    $ttl = 600; // 10 minut

    if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        return trim((string) file_get_contents($cacheFile));
    }

    $ip = '';
    $context = stream_context_create(['http' => ['timeout' => 2], 'https' => ['timeout' => 2]]);
    $result = @file_get_contents('https://api.ipify.org', false, $context);
    if ($result !== false && filter_var(trim($result), FILTER_VALIDATE_IP)) {
        $ip = trim($result);
        @file_put_contents($cacheFile, $ip);
    } elseif (is_readable($cacheFile)) {
        $ip = trim((string) file_get_contents($cacheFile));
    }

    return $ip;
}

/**
 * Sieć: transfer upload/download (bieżąca prędkość liczona z dwóch próbek /proc/net/dev),
 * liczba aktywnych połączeń, transfer dzienny (kumulowany w SQLite).
 */
function get_network_info(): array
{
    $iface = detect_primary_interface();
    $sample1 = read_iface_bytes($iface);
    usleep(300000);
    $sample2 = read_iface_bytes($iface);

    $rxRate = $sample2 && $sample1 ? max(0, ($sample2['rx'] - $sample1['rx']) / 0.3) : 0;
    $txRate = $sample2 && $sample1 ? max(0, ($sample2['tx'] - $sample1['tx']) / 0.3) : 0;

    $connections = count_active_connections();
    $daily = get_daily_network_usage($sample2['rx'] ?? 0, $sample2['tx'] ?? 0);

    return [
        'interface' => $iface,
        'download_bps' => round($rxRate),
        'upload_bps' => round($txRate),
        'connections' => $connections,
        'daily_rx' => $daily['rx_bytes'],
        'daily_tx' => $daily['tx_bytes'],
    ];
}

function detect_primary_interface(): string
{
    $route = safe_exec('ip', ['route', 'get', '1']);
    if ($route !== '' && preg_match('/dev\s+(\S+)/', $route, $m)) {
        return $m[1];
    }
    return 'eth0';
}

function read_iface_bytes(string $iface): ?array
{
    $data = @file_get_contents('/proc/net/dev');
    if ($data === false) {
        return null;
    }
    foreach (explode("\n", $data) as $line) {
        if (str_contains($line, $iface . ':')) {
            $parts = preg_split('/\s+/', trim(str_replace($iface . ':', '', $line)));
            return ['rx' => (int) ($parts[0] ?? 0), 'tx' => (int) ($parts[8] ?? 0)];
        }
    }
    return null;
}

function count_active_connections(): int
{
    $out = safe_exec('ss', ['-tun']);
    if ($out === '') {
        $out = safe_exec('netstat', ['-tun']);
    }
    if ($out === '') {
        return 0;
    }
    $lines = explode("\n", trim($out));
    return max(0, count($lines) - 1); // odjęcie nagłówka
}

/** Zapisuje/aktualizuje dzienny licznik transferu w SQLite i zwraca bieżącą wartość dnia. */
function get_daily_network_usage(int $currentRx, int $currentTx): array
{
    $pdo = history_db();
    $today = date('Y-m-d');
    $cacheKey = DATA_DIR . '/network_baseline.json';

    $baseline = [];
    if (is_readable($cacheKey)) {
        $baseline = json_decode((string) file_get_contents($cacheKey), true) ?: [];
    }

    // Nowy dzień, brak zapisanego punktu odniesienia, lub restart systemu
    // (licznik interfejsu mniejszy niż zapisana baza - zresetowany przez jądro).
    $needsReset = ($baseline['day'] ?? '') !== $today
        || !isset($baseline['rx0'], $baseline['tx0'])
        || $currentRx < $baseline['rx0']
        || $currentTx < $baseline['tx0'];

    if ($needsReset) {
        $baseline = ['day' => $today, 'rx0' => $currentRx, 'tx0' => $currentTx];
        file_put_contents($cacheKey, json_encode($baseline));
    }

    $rxToday = max(0, $currentRx - $baseline['rx0']);
    $txToday = max(0, $currentTx - $baseline['tx0']);

    $stmt = $pdo->prepare('
        INSERT INTO network_daily (day, rx_bytes, tx_bytes) VALUES (:d, :rx, :tx)
        ON CONFLICT(day) DO UPDATE SET rx_bytes = :rx2, tx_bytes = :tx2
    ');
    $stmt->execute([':d' => $today, ':rx' => $rxToday, ':tx' => $txToday, ':rx2' => $rxToday, ':tx2' => $txToday]);

    return ['rx_bytes' => $rxToday, 'tx_bytes' => $txToday];
}

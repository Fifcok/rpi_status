<?php
/**
 * Lista certyfikatów SSL/TLS znalezionych na serwerze (domyślnie Let's Encrypt)
 * wraz z domeną, wystawcą, datą ważności i liczbą pozostałych dni.
 */

declare(strict_types=1);

/** Dodatkowe ścieżki do certyfikatów poza domyślnym katalogiem Let's Encrypt. */
const EXTRA_CERT_PATHS = [
    // '/etc/ssl/certs/moj-certyfikat.pem',
];

function get_ssl_certificates(): array
{
    if (!command_exists('openssl')) {
        return [];
    }

    $certFiles = [];

    foreach (glob('/etc/letsencrypt/live/*/fullchain.pem') ?: [] as $path) {
        $certFiles[] = $path;
    }
    foreach (EXTRA_CERT_PATHS as $path) {
        if (is_readable($path)) {
            $certFiles[] = $path;
        }
    }

    $certificates = [];
    foreach ($certFiles as $path) {
        $info = read_certificate_info($path);
        if ($info !== null) {
            $certificates[] = $info;
        }
    }

    usort($certificates, static function ($a, $b) {
        return $a['days_left'] <=> $b['days_left'];
    });

    return $certificates;
}

function read_certificate_info(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }

    $output = safe_exec('openssl', ['x509', '-in', $path, '-noout', '-subject', '-issuer', '-enddate']);
    if ($output === '') {
        return null;
    }

    $domain = 'nieznana';
    $issuer = 'nieznany';
    $endDate = null;

    if (preg_match('/subject=.*?CN\s*=\s*([^,\/\n]+)/i', $output, $m)) {
        $domain = trim($m[1]);
    }
    if (preg_match('/issuer=.*?CN\s*=\s*([^,\/\n]+)/i', $output, $m)) {
        $issuer = trim($m[1]);
    }
    if (preg_match('/notAfter=(.+)/', $output, $m)) {
        $endDate = strtotime(trim($m[1]));
    }

    if ($endDate === null || $endDate === false) {
        return null;
    }

    $daysLeft = (int) floor(($endDate - time()) / 86400);

    return [
        'domain' => $domain,
        'issuer' => $issuer,
        'valid_until' => date('Y-m-d', $endDate),
        'days_left' => $daysLeft,
        'status' => ssl_status_color($daysLeft),
        'path' => $path,
    ];
}

/** Zwraca "green" / "yellow" / "red" wg liczby pozostałych dni. */
function ssl_status_color(int $daysLeft): string
{
    if ($daysLeft < 0) {
        return 'red';
    }
    if ($daysLeft <= 14) {
        return 'red';
    }
    if ($daysLeft <= 30) {
        return 'yellow';
    }
    return 'green';
}

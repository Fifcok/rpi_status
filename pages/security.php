<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../includes/security_info.php';

$recentLogins = get_recent_ssh_logins(15);
$failedLogins = get_failed_ssh_logins(15);
$summary = get_failed_login_summary(15);
$topAttackers = $summary['top_ips'];
$topUsernames = $summary['top_usernames'];
$firewall = get_firewall_status();
$fail2ban = get_fail2ban_status();

$pageTitle = 'Bezpieczeństwo';
$activePage = 'security';
$pageScripts = ['/assets/js/pages/security.js'];

require APP_ROOT . '/includes/header.php';
?>
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">Bezpieczeństwo</h1>
    <button class="btn btn-outline-secondary btn-sm" id="refreshSecurity"><i class="bi bi-arrow-clockwise"></i> Odśwież</button>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <div class="tile-header"><i class="bi bi-shield-fill-check tile-icon"></i><span class="tile-title">Firewall</span></div>
            <div class="h5 mt-2" id="firewallStatus">
                <span class="badge <?= $firewall['active'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                    <?= h($firewall['engine']) ?>: <?= $firewall['active'] === null ? 'nieznany' : ($firewall['active'] ? 'aktywny' : 'nieaktywny') ?>
                </span>
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <div class="tile-header"><i class="bi bi-shield-lock-fill tile-icon"></i><span class="tile-title">Fail2ban</span></div>
            <div class="h5 mt-2" id="fail2banStatus">
                <?php if (!$fail2ban['installed']): ?>
                    <span class="badge text-bg-secondary">niezainstalowany</span>
                <?php else: ?>
                    <span class="badge <?= $fail2ban['active'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $fail2ban['active'] ? 'aktywny' : 'nieaktywny' ?></span>
                    <span class="text-muted small ms-2"><?= h(implode(', ', $fail2ban['jails']) ?: 'brak jaili') ?></span>
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Ostatnie logowania SSH</div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0">
                    <thead><tr><th>Użytkownik</th><th>IP</th></tr></thead>
                    <tbody id="recentLoginsBody">
                    <?php foreach ($recentLogins as $login): ?>
                        <tr><td><?= h($login['user']) ?></td><td><?= h($login['ip']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$recentLogins): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Nieudane logowania</div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0">
                    <thead><tr><th>Użytkownik</th><th>IP</th></tr></thead>
                    <tbody id="failedLoginsBody">
                    <?php foreach ($failedLogins as $login): ?>
                        <tr><td><?= h($login['user']) ?></td><td><?= h($login['ip']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$failedLogins): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Top 15 w tym miesiącu — najczęściej atakujące adresy IP</span>
                <span class="text-muted small" id="attackersSummary">łącznie <?= h((string) $summary['total_attempts']) ?><?= $summary['truncated'] ? '+' : '' ?> prób z <?= h((string) $summary['total_unique_ips']) ?> adresów</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0">
                    <thead><tr><th>IP</th><th>Liczba prób</th></tr></thead>
                    <tbody id="attackersBody">
                    <?php foreach ($topAttackers as $attacker): ?>
                        <tr><td><?= h($attacker['ip']) ?></td><td><?= h((string) $attacker['attempts']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$topAttackers): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Top 15 w tym miesiącu — najczęściej próbowane loginy</span>
                <span class="text-muted small" id="usernamesSummary">łącznie <?= h((string) $summary['total_attempts']) ?><?= $summary['truncated'] ? '+' : '' ?> prób z <?= h((string) $summary['total_unique_usernames']) ?> loginów</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-dark-custom mb-0">
                    <thead><tr><th>Login</th><th>Liczba prób</th></tr></thead>
                    <tbody id="usernamesBody">
                    <?php foreach ($topUsernames as $entry): ?>
                        <tr><td><?= h($entry['user']) ?></td><td><?= h((string) $entry['attempts']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$topUsernames): ?>
                        <tr><td colspan="2" class="text-center text-muted py-4">Brak danych.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small mt-2 mb-0">
    <i class="bi bi-info-circle"></i> Ranking obejmuje pełną historię dostępną w logu <code>btmp</code> — system domyślnie rotuje ten plik co miesiąc, więc w praktyce to zwykle dane od początku bieżącego miesiąca.
</p>

<?php require APP_ROOT . '/includes/footer.php'; ?>

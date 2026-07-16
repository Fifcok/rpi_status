<?php
/**
 * Menu boczne nawigacji. Wykorzystuje $activePage ustawione przez stronę wywołującą.
 */

declare(strict_types=1);

$navItems = [
    'dashboard'  => ['label' => 'Dashboard',        'href' => '/index.php',              'icon' => 'bi-speedometer2'],
    'services'   => ['label' => 'Usługi',           'href' => '/pages/services.php',     'icon' => 'bi-hdd-network'],
    'docker'     => ['label' => 'Docker',           'href' => '/pages/docker.php',        'icon' => 'bi-box-seam'],
    'processes'  => ['label' => 'Procesy',          'href' => '/pages/processes.php',     'icon' => 'bi-list-task'],
    'logs'       => ['label' => 'Logi',             'href' => '/pages/logs.php',          'icon' => 'bi-file-text'],
    'cron'       => ['label' => 'Cron',             'href' => '/pages/cron.php',          'icon' => 'bi-clock-history'],
    'backup'     => ['label' => 'Backup',           'href' => '/pages/backup.php',        'icon' => 'bi-archive'],
    'ssl'        => ['label' => 'SSL',              'href' => '/pages/ssl.php',           'icon' => 'bi-shield-lock'],
    'databases'  => ['label' => 'Bazy danych',      'href' => '/pages/databases.php',     'icon' => 'bi-database'],
    'security'   => ['label' => 'Bezpieczeństwo',   'href' => '/pages/security.php',      'icon' => 'bi-shield-exclamation'],
];
?>
<aside class="app-sidebar" id="appSidebar">
    <nav class="nav flex-column gap-1 p-2">
        <?php foreach ($navItems as $key => $item): ?>
        <a class="nav-link sidebar-link<?= $activePage === $key ? ' active' : '' ?>" href="<?= h($item['href']) ?>">
            <i class="bi <?= h($item['icon']) ?>"></i>
            <span><?= h($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

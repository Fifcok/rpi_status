<?php
/**
 * Menu boczne nawigacji. Wykorzystuje $activePage ustawione przez stronę wywołującą.
 */

declare(strict_types=1);

$navItems = [
    'dashboard'  => ['label' => 'Dashboard',        'href' => BASE_PATH . '/index.php',              'icon' => 'bi-speedometer2'],
    'services'   => ['label' => 'Usługi',           'href' => BASE_PATH . '/pages/services.php',     'icon' => 'bi-hdd-network'],
    'docker'     => ['label' => 'Docker',           'href' => BASE_PATH . '/pages/docker.php',        'icon' => 'bi-box-seam'],
    'processes'  => ['label' => 'Procesy',          'href' => BASE_PATH . '/pages/processes.php',     'icon' => 'bi-list-task'],
    'logs'       => ['label' => 'Logi',             'href' => BASE_PATH . '/pages/logs.php',          'icon' => 'bi-file-text'],
    'cron'       => ['label' => 'Cron',             'href' => BASE_PATH . '/pages/cron.php',          'icon' => 'bi-clock-history'],
    'backup'     => ['label' => 'Backup',           'href' => BASE_PATH . '/pages/backup.php',        'icon' => 'bi-archive'],
    'ssl'        => ['label' => 'SSL',              'href' => BASE_PATH . '/pages/ssl.php',           'icon' => 'bi-shield-lock'],
    'databases'  => ['label' => 'Bazy danych',      'href' => BASE_PATH . '/pages/databases.php',     'icon' => 'bi-database'],
    'security'   => ['label' => 'Bezpieczeństwo',   'href' => BASE_PATH . '/pages/security.php',      'icon' => 'bi-shield-exclamation'],
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

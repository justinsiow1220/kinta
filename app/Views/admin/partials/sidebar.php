<?php
$currentPath = trim(service('uri')->getPath(), '/');
$adminNavItems = [
    ['label' => 'Dashboard', 'path' => 'admin', 'icon' => 'bi-speedometer2'],
    ['label' => 'Workforce', 'path' => 'admin/workforce', 'icon' => 'bi-people'],
    ['label' => 'Machines', 'path' => 'admin/machines', 'icon' => 'bi-gear'],
    ['label' => 'Sectors', 'path' => 'admin/sectors', 'icon' => 'bi-layers'],
    ['label' => 'Activities', 'path' => 'admin/activities', 'icon' => 'bi-clipboard-check'],
    ['label' => 'Sales Orders', 'path' => 'admin/sales-orders', 'icon' => 'bi-cart3'],
    ['label' => 'Problems', 'path' => 'admin/problems', 'icon' => 'bi-exclamation-triangle'],
    ['label' => 'Operation reports', 'path' => 'admin/operations', 'icon' => 'bi-file-earmark-bar-graph', 'prefix' => true],
];
?>

<aside class="sidebar" id="admin-sidebar" aria-label="Admin navigation">
    <div class="sidebar-brand">
        <img
            class="brand-icon"
            src="<?= base_url('assets/favicon.png') ?>"
            alt=""
        >
        <span class="brand-label">KINTA Admin</span>
    </div>

    <nav class="admin-nav" id="admin-nav" aria-label="Admin pages">
        <?php foreach ($adminNavItems as $item): ?>
            <?php
            $active = ! empty($item['prefix'])
                ? ($currentPath === $item['path'] || str_starts_with($currentPath, $item['path'] . '/'))
                : $currentPath === $item['path'];
            ?>
            <a
                href="<?= site_url($item['path']) ?>"
                class="<?= $active ? 'active' : '' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>
            >
                <i class="bi <?= esc($item['icon']) ?> nav-icon" aria-hidden="true"></i>
                <span class="nav-label"><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </nav>

    <div class="sidebar-signout">
        <form method="post" action="<?= site_url('admin/logout') ?>">
            <?= csrf_field() ?>
            <button type="submit" aria-label="Sign out">
                <i class="bi bi-box-arrow-right nav-icon" aria-hidden="true"></i>
                <span class="nav-label">Sign out</span>
            </button>
        </form>
    </div>
</aside>

<button
    class="sidebar-toggle"
    id="sidebar-toggle"
    type="button"
    aria-expanded="false"
    aria-controls="admin-sidebar"
    aria-label="Open navigation menu"
    title="Open navigation menu"
>
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>
</button>

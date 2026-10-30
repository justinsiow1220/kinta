<!doctype html>
<html lang="en">
<head>
    <?= $this->include('admin/partials/head', [
        'title' => $title,
        'masterDataModule' => $masterDataModule ?? false,
        'adminModalEnabled' => $adminModalEnabled ?? false,
    ]) ?>
</head>
<body>
    <div class="admin-layout">
        <?= $this->include('admin/partials/sidebar') ?>

        <div class="page-content">
            <div class="page-toolbar">
                <div>
                    <h2 class="page-heading"><?= esc($title) ?></h2>
                    <ol class="breadcrumb">
                        <li>
                            <a href="<?= site_url('admin') ?>">Dashboard</a>
                        </li>

                        <?php foreach (($breadcrumbs ?? []) as $breadcrumb): ?>
                            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>
                            <li>
                                <?php if (! empty($breadcrumb['url'] ?? null)): ?>
                                    <a href="<?= esc($breadcrumb['url']) ?>">
                                        <?= esc($breadcrumb['label'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <?= esc($breadcrumb['label'] ?? '') ?>
                                <?php endif ?>
                            </li>
                        <?php endforeach ?>

                        <?php if (empty($breadcrumbs ?? [])): ?>
                            <li class="breadcrumb-separator" aria-hidden="true">&rsaquo;</li>
                            <li aria-current="page"><?= esc($title) ?></li>
                        <?php endif ?>
                    </ol>
                </div>

                <?php if (! empty($toolbarActions ?? '')): ?>
                    <div class="toolbar-actions">
                        <?= $toolbarActions ?>
                    </div>
                <?php endif ?>
            </div>

            <main><?= $content ?></main>
        </div>
    </div>

    <?= $this->include('admin/partials/scripts', [
        'masterDataModule' => $masterDataModule ?? false,
        'adminModalEnabled' => $adminModalEnabled ?? false,
    ]) ?>
</body>
</html>

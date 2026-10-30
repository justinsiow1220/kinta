<dialog
    class="admin-form-modal"
    id="<?= esc($id, 'attr') ?>"
    data-admin-modal
    data-reopen="<?= $reopen ? 'true' : 'false' ?>"
    aria-labelledby="<?= esc($id . '-title', 'attr') ?>"
    <?= ($openWithoutJavaScript ?? false) ? 'open' : '' ?>
    <?php if ($error): ?>aria-describedby="<?= esc($id . '-error', 'attr') ?>"<?php endif ?>
>
    <div class="admin-form-modal__panel">
        <header class="admin-form-modal__header">
            <h2 id="<?= esc($id . '-title', 'attr') ?>" data-admin-modal-title><?= esc($title) ?></h2>
            <button
                type="button"
                class="admin-form-modal__close"
                data-modal-close
                aria-label="Close dialog"
            >&times;</button>
        </header>

        <p
            class="error admin-form-modal__error"
            role="alert"
            id="<?= esc($id, 'attr') ?>-error"
            <?= $error ? '' : 'hidden' ?>
        ><?= $error ? esc($error) : '' ?></p>

        <div class="admin-form-modal__body">
            <?= $content ?>
        </div>

        <footer class="admin-form-modal__footer">
            <?php if (isset($footerContent)): ?>
                <?= $footerContent ?>
            <?php else: ?>
                <button type="button" data-modal-close>Cancel</button>
            <?php endif ?>
        </footer>
    </div>
</dialog>

<?php
$isEnhancedMaster = in_array($kind, ['activities', 'machines', 'sectors', 'sales-orders', 'problems'], true);
$masterLabel = match ($kind) {
    'activities' => 'Activity',
    'machines' => 'Machine',
    'sectors' => 'Sector',
    'sales-orders' => 'Sales Order',
    'problems' => 'Problem',
    default => 'record',
};
$modalId = match ($kind) {
    'activities' => 'activity-form-modal',
    'machines' => 'machine-form-modal',
    'sectors' => 'sector-form-modal',
    'sales-orders' => 'sales-order-form-modal',
    'problems' => 'problem-form-modal',
    default => $kind . '-form-modal',
};
$tableId = $kind . '-master-table';
$editRecord = $editRecord ?? null;
$formError = session()->getFlashdata('error');
$masterForm = view('admin/partials/master-data/form', [
    'kind' => $kind,
    'fields' => $fields,
    'editRecord' => $editRecord,
    'sectors' => $sectors,
    'isEnhancedMaster' => $isEnhancedMaster,
    'modalId' => $modalId,
]);
?>
<?php ob_start(); ?>

<?php if ($formError && ! $isEnhancedMaster): ?>
    <p class="error" role="alert"><?= esc($formError) ?></p>
<?php endif ?>

<?php if (session()->getFlashdata('message')): ?>
    <p><?= esc(session()->getFlashdata('message')) ?></p>
<?php endif ?>

<?php if ($isEnhancedMaster): ?>
    <p id="<?= esc($kind) ?>-ajax-success" role="status" aria-live="polite" hidden></p>
    <p id="<?= esc($kind) ?>-ajax-error" class="error" role="alert" aria-live="assertive" hidden></p>
    <div class="master-form-toolbar">
        <button type="button" onclick="openMasterCreate('<?= esc($kind, 'attr') ?>')">Add <?= esc($masterLabel) ?></button>
    </div>
    <?= view('admin/partials/form_modal', [
        'id' => $modalId,
        'title' => (old('id') || $editRecord) ? 'Edit ' . $masterLabel : 'Add ' . $masterLabel,
        'openWithoutJavaScript' => true,
        'content' => $masterForm,
        'footerContent' => '<button type="submit" form="edit-form" data-master-submit>Save</button>',
        'error' => $formError,
        'reopen' => (bool) ($formError || $editRecord),
    ]) ?>
    <script>
        (() => {
            const dialog = document.getElementById(<?= json_encode($modalId) ?>);
            if (dialog && dialog.open) {
                dialog.close();
            }
        })();
    </script>
<?php else: ?>
    <section>
        <h2 id="form-title">Add record</h2>
        <?= $masterForm ?>
    </section>
<?php endif ?>

<?= view('admin/partials/master-data/table', [
    'kind' => $kind,
    'fields' => $fields,
    'rows' => $rows,
    'isEnhancedMaster' => $isEnhancedMaster,
    'masterLabel' => $masterLabel,
    'tableId' => $tableId,
]) ?>

<script>
    function resetForm() {
        document.getElementById('edit-form').reset();
        document.getElementById('record-id').value = '';
        const formTitle = document.querySelector('[data-admin-modal-title]') || document.getElementById('form-title');
        if (formTitle) {
            formTitle.textContent = <?= json_encode($isEnhancedMaster ? 'Add ' . $masterLabel : 'Add record') ?>;
        }

        const sectorSelect = document.getElementById('field-sector_id');
        if (sectorSelect) {
            sectorSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const modalError = document.getElementById(<?= json_encode($modalId . '-error') ?>);
        if (modalError) {
            modalError.textContent = '';
            modalError.hidden = true;
        }

        const successMessage = document.getElementById(<?= json_encode($kind . '-ajax-success') ?>);
        if (successMessage) {
            successMessage.textContent = '';
            successMessage.hidden = true;
        }

        const pageErrorMessage = document.getElementById(<?= json_encode($kind . '-ajax-error') ?>);
        if (pageErrorMessage) {
            pageErrorMessage.textContent = '';
            pageErrorMessage.hidden = true;
        }
    }

    function openMasterCreate(module) {
        resetForm();
        const modalIds = {
            activities: 'activity-form-modal',
            machines: 'machine-form-modal',
            sectors: 'sector-form-modal',
            'sales-orders': 'sales-order-form-modal',
            problems: 'problem-form-modal',
        };
        const modalId = modalIds[module];
        if (!modalId) return;
        window.openAdminModal(modalId);
    }

    function openActivityCreate() {
        openMasterCreate('activities');
    }

    function editRecord(row) {
        document.getElementById('record-id').value = row.id;
        <?php foreach ($fields as $field => $label): ?>
            document.getElementById('field-<?= esc($field) ?>').value = row['<?= esc($field) ?>'] ?? '';
        <?php endforeach ?>
        const sectorSelect = document.getElementById('field-sector_id');
        if (sectorSelect) {
            sectorSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        <?php if ($isEnhancedMaster): ?>
            document.querySelector('[data-admin-modal-title]').textContent = <?= json_encode('Edit ' . $masterLabel) ?>;
            window.openAdminModal(<?= json_encode($modalId) ?>);
        <?php else: ?>
            document.getElementById('form-title').textContent = 'Edit record #' + row.id;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        <?php endif ?>
    }
</script>

<?php
$content = ob_get_clean();
echo view('admin/shell', [
    'title' => $title,
    'content' => $content,
    'masterDataModule' => $isEnhancedMaster ? $kind : false,
    'adminModalEnabled' => $isEnhancedMaster,
]);
?>

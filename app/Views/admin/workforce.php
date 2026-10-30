<?php
$formError = session()->getFlashdata('error');
$formMessage = session()->getFlashdata('message');
$editRecord = $editRecord ?? null;
$formContent = view('admin/partials/workforce-form', [
    'editRecord' => $editRecord,
]);
ob_start();
?>

<?php if ($formMessage): ?>
    <p role="status" aria-live="polite"><?= esc($formMessage) ?></p>
<?php endif ?>

<p id="workforce-ajax-success" role="status" aria-live="polite" hidden></p>
<p id="workforce-ajax-error" class="error" role="alert" aria-live="assertive" hidden></p>

<div class="master-form-toolbar">
    <button type="button" onclick="openWorkforceCreate()">Add Workforce</button>
</div>

<?= view('admin/partials/form_modal', [
    'id' => 'workforce-form-modal',
    'title' => (old('id') || $editRecord) ? 'Edit Workforce Member' : 'Add Workforce Member',
    'openWithoutJavaScript' => true,
    'content' => $formContent,
    'footerContent' => '<button type="submit" form="edit-form" data-master-submit>Save</button>',
    'error' => $formError,
    'reopen' => (bool) ($formError || $editRecord),
]) ?>

<script>
    (() => {
        const dialog = document.getElementById('workforce-form-modal');
        if (dialog && dialog.open) {
            dialog.close();
        }
    })();
</script>

<div class="master-data-table-scroll">
    <table
        id="workforce-master-table"
        class="master-data-table"
        data-master-data-table
        data-master-module="workforce"
    >
        <thead>
            <tr>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Username</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $status = strtolower(trim((string) ($row['status'] ?? '')));
                    $statusLabel = match ($status) {
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        default => 'Unknown',
                    };
                    $statusClass = match ($status) {
                        'active' => 'kinta-status-active',
                        'inactive' => 'kinta-status-inactive',
                        default => 'kinta-status-neutral',
                    };
                ?>
                <tr id="workforce-row-<?= (int) $row['id'] ?>">
                    <td><?= esc($row['staff_id']) ?></td>
                    <td><?= esc($row['name']) ?></td>
                    <td><?= esc($row['role']) ?></td>
                    <td><?= esc($row['username'] ?? '-') ?></td>
                    <td data-order="<?= esc($statusLabel, 'attr') ?>" data-search="<?= esc($statusLabel, 'attr') ?>">
                        <span class="kinta-status-badge <?= esc($statusClass, 'attr') ?>"><?= esc($statusLabel) ?></span>
                    </td>
                    <td>
                        <?php if ($row['role'] === 'admin'): ?>
                            <span class="muted">-</span>
                        <?php else: ?>
                            <button
                                type="button"
                                class="kinta-master-edit-button"
                                aria-label="Edit workforce member"
                                title="Edit workforce member"
                                onclick='editWf(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                            ><i class="bi bi-pencil" aria-hidden="true"></i></button>
                            <noscript>
                                <a href="<?= site_url('admin/workforce?edit=' . (int) $row['id']) ?>">Edit</a>
                            </noscript>
                            <form
                                class="inline"
                                method="post"
                                action="<?= site_url('admin/workforce/' . (int) $row['id'] . '/toggle') ?>"
                                data-master-status-form="true"
                            >
                                <?= csrf_field() ?>
                                <button type="submit"><?= $status === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<script>
    (() => {
        const roleSelect = document.getElementById('wf-role');
        const nameInput = document.getElementById('wf-name');
        const usernameInput = document.getElementById('wf-username');
        const passwordInput = document.getElementById('wf-password');
        const loginFields = document.getElementById('login-fields');
        const form = document.getElementById('edit-form');

        function updateRole() {
            const isSupervisor = roleSelect.value === 'supervisor';
            loginFields.hidden = !isSupervisor;
            usernameInput.required = isSupervisor;
            passwordInput.required = isSupervisor && document.getElementById('record-id').value === '';
        }

        roleSelect.addEventListener('change', updateRole);

        window.resetWf = () => {
            form.reset();
            document.getElementById('record-id').value = '';
            roleSelect.value = 'supervisor';
            nameInput.value = '';
            usernameInput.value = '';
            passwordInput.value = '';
            document.querySelector('[data-admin-modal-title]').textContent = 'Add Workforce Member';
            if (window.jQuery?.fn?.select2 && window.jQuery(roleSelect).hasClass('select2-hidden-accessible')) {
                window.jQuery(roleSelect).val('supervisor').trigger('change');
            } else {
                roleSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            updateRole();
        };

        window.openWorkforceCreate = () => {
            window.resetWf();
            window.openAdminModal('workforce-form-modal');
        };

        window.editWf = (row) => {
            document.getElementById('record-id').value = row.id;
            nameInput.value = row.name || '';
            roleSelect.value = row.role;
            usernameInput.value = row.username || '';
            passwordInput.value = '';
            document.querySelector('[data-admin-modal-title]').textContent = `Edit ${row.staff_id}`;
            if (window.jQuery?.fn?.select2 && window.jQuery(roleSelect).hasClass('select2-hidden-accessible')) {
                window.jQuery(roleSelect).val(row.role).trigger('change');
            } else {
                roleSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            updateRole();
            window.openAdminModal('workforce-form-modal');
        };

        window.editRecord = window.editWf;
        updateRole();
    })();
</script>

<?php
$content = ob_get_clean();
echo view('admin/shell', [
    'title' => $title,
    'content' => $content,
    'masterDataModule' => 'workforce',
    'adminModalEnabled' => true,
]);
?>

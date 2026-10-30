<form
    method="post"
    action="<?= site_url('admin/workforce') ?>"
    id="edit-form"
    data-master-ajax="true"
    data-master-module="workforce"
    data-modal-id="workforce-form-modal"
    data-csrf-name="<?= esc(csrf_token(), 'attr') ?>"
>
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="record-id" value="<?= esc(old('id') ?? ($editRecord['id'] ?? ''), 'attr') ?>">

    <label>
        Name
        <input name="name" id="wf-name" value="<?= esc(old('name') ?? ($editRecord['name'] ?? ''), 'attr') ?>" required>
    </label>

    <label>
        Role
        <?php $selectedRole = old('role') ?? ($editRecord['role'] ?? 'supervisor'); ?>
        <select name="role" id="wf-role" required>
            <?php foreach (['supervisor' => 'Supervisor', 'operator' => 'Operator', 'qc' => 'QC'] as $role => $label): ?>
                <option value="<?= esc($role, 'attr') ?>" <?= $selectedRole === $role ? 'selected' : '' ?>>
                    <?= esc($label) ?>
                </option>
            <?php endforeach ?>
        </select>
    </label>

    <div id="login-fields">
        <label>
            Username
            <input
                name="username"
                id="wf-username"
                value="<?= esc(old('username') ?? ($editRecord['username'] ?? ''), 'attr') ?>"
                autocomplete="username"
            >
        </label>
        <label>
            Password
            <input name="password" id="wf-password" type="password" minlength="8" autocomplete="new-password">
            <small>Required for new supervisors; leave blank to keep an existing password.</small>
        </label>
    </div>
</form>

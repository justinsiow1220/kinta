<form
    method="post"
    action="<?= site_url('admin/' . $kind) ?>"
    id="edit-form"
    <?php if ($isEnhancedMaster): ?>
        data-master-ajax="true"
        data-master-module="<?= esc($kind, 'attr') ?>"
        data-modal-id="<?= esc($modalId, 'attr') ?>"
        data-csrf-name="<?= esc(csrf_token(), 'attr') ?>"
    <?php endif ?>
>
    <?= csrf_field() ?>
    <input
        type="hidden"
        name="id"
        id="record-id"
        value="<?= $isEnhancedMaster ? esc(old('id') ?? ($editRecord['id'] ?? ''), 'attr') : '' ?>"
    >

    <?php foreach ($fields as $field => $label): ?>
        <label>
            <?= esc($label) ?>

            <?php if ($field === 'sector_id'): ?>
                <?php $oldSectorId = $kind === 'activities' ? (string) (old('sector_id') ?? '') : ''; ?>
                <select name="sector_id" id="field-sector_id" required>
                    <option value="">Choose sector</option>
                    <?php foreach ($sectors as $sector): ?>
                        <option
                            value="<?= (int) $sector['id'] ?>"
                            <?= $oldSectorId === (string) $sector['id'] ? 'selected' : '' ?>
                        >
                            <?= esc($sector['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            <?php else: ?>
                <input
                    name="<?= esc($field) ?>"
                    id="field-<?= esc($field) ?>"
                    value="<?= $isEnhancedMaster ? esc(old($field) ?? ($editRecord[$field] ?? ''), 'attr') : '' ?>"
                    <?= $kind === 'machines' && $field === 'machine_code' ? 'maxlength="50"' : '' ?>
                    <?= $kind === 'machines' && $field === 'machine_name' ? 'maxlength="150"' : '' ?>
                    <?= $kind === 'problems' && $field === 'code' ? 'maxlength="50"' : '' ?>
                    <?= $kind === 'problems' && $field === 'name' ? 'maxlength="150"' : '' ?>
                    <?= $field === 'batch_qty' ? 'type="number" min="0"' : 'type="text"' ?>
                    required
                >
            <?php endif ?>
        </label>
    <?php endforeach ?>

    <?php if (! $isEnhancedMaster): ?>
        <button type="submit">Save</button>
        <button type="button" onclick="resetForm()">Clear</button>
    <?php endif ?>
</form>

<section>
    <?php if ($isEnhancedMaster): ?>
        <div class="master-data-table-scroll">
    <?php endif ?>
    <table
        <?php if ($isEnhancedMaster): ?>
            id="<?= esc($tableId, 'attr') ?>"
            class="master-data-table"
            data-master-data-table
            data-master-module="<?= esc($kind, 'attr') ?>"
        <?php endif ?>
    >
        <thead>
            <tr>
                <th>ID</th>
                <?php foreach ($fields as $field => $label): ?>
                    <th><?= esc($label) ?></th>
                <?php endforeach ?>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr <?php if ($isEnhancedMaster): ?>id="<?= esc($kind, 'attr') ?>-row-<?= (int) $row['id'] ?>"<?php endif ?>>
                    <td><?= (int) $row['id'] ?></td>

                    <?php foreach ($fields as $field => $label): ?>
                        <td>
                            <?= esc($field === 'sector_id'
                                ? ($row['sector_name'] ?? $row[$field] ?? '')
                                : ($row[$field] ?? '')) ?>
                        </td>
                    <?php endforeach ?>

                    <?php if ($isEnhancedMaster): ?>
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
                        <td data-order="<?= esc($statusLabel, 'attr') ?>" data-search="<?= esc($statusLabel, 'attr') ?>">
                            <span class="kinta-status-badge <?= esc($statusClass, 'attr') ?>">
                                <?= esc($statusLabel) ?>
                            </span>
                        </td>
                    <?php else: ?>
                        <td class="<?= $row['status'] === 'active' ? '' : 'inactive' ?>">
                            <?= esc($row['status']) ?>
                        </td>
                    <?php endif ?>
                    <td>
                        <button
                            type="button"
                            <?php if ($isEnhancedMaster): ?>class="kinta-master-edit-button" aria-label="Edit <?= esc($masterLabel, 'attr') ?>" title="Edit <?= esc($masterLabel, 'attr') ?>"<?php endif ?>
                            onclick='editRecord(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                        ><?php if ($isEnhancedMaster): ?><i class="bi bi-pencil" aria-hidden="true"></i><?php else: ?>Edit<?php endif ?></button>
                        <?php if (in_array($kind, ['machines', 'sectors', 'sales-orders', 'problems'], true)): ?>
                            <noscript>
                                <a class="kinta-nojs-edit-link" href="<?= site_url('admin/' . $kind . '?edit=' . (int) $row['id']) ?>">Edit</a>
                            </noscript>
                        <?php endif ?>
                        <form
                            class="inline"
                            method="post"
                            action="<?= site_url('admin/' . $kind . '/' . $row['id'] . '/toggle') ?>"
                            <?php if ($isEnhancedMaster): ?>data-master-status-form="true"<?php endif ?>
                        >
                            <?= csrf_field() ?>
                            <button <?php if ($isEnhancedMaster): ?>type="submit"<?php endif ?>><?= $row['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php if ($isEnhancedMaster): ?>
        </div>
    <?php endif ?>
</section>

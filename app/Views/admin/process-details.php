<?php
$status = strtolower(trim((string) ($row['status'] ?? '')));
$statusLabel = $status === '' ? 'Unknown' : ucwords(str_replace(['_', '-'], ' ', $status));
$statusClass = match ($status) {
    'completed' => 'is-complete',
    'running', 'in_progress' => 'is-running',
    'cancelled', 'failed' => 'is-stopped',
    default => 'is-neutral',
};
$notRecorded = 'Not recorded';
$sections = [
    'operators' => [
        'title' => 'Operators',
        'empty' => 'No operators are recorded for this process.',
        'columns' => [
            'staff_id' => 'Staff ID',
            'name' => 'Name',
            'assigned_at' => 'Assigned at',
            'removed_at' => 'Removed at',
        ],
    ],
    'qc' => [
        'title' => 'QC inspections',
        'empty' => 'No QC inspections are recorded for this process.',
        'columns' => [
            'staff_id' => 'Staff ID',
            'name' => 'QC personnel',
            'quantity' => 'Inspected quantity',
            'inspection_time' => 'Inspection time',
        ],
    ],
    'problems' => [
        'title' => 'Problems',
        'empty' => 'No problems are recorded for this process.',
        'columns' => [
            'code' => 'Problem code',
            'name' => 'Problem',
            'created_at' => 'Reported at',
        ],
    ],
    'pics' => [
        'title' => 'PIC history',
        'empty' => 'No PIC changes are recorded for this process.',
        'columns' => [
            'staff_id' => 'Staff ID',
            'name' => 'Supervisor',
            'started_at' => 'Started at',
            'ended_at' => 'Ended at',
        ],
    ],
];
$timeFields = ['assigned_at', 'removed_at', 'inspection_time', 'created_at', 'started_at', 'ended_at'];
ob_start();
?>

<section class="process-details-summary" aria-labelledby="process-summary-title">
    <div class="process-details-heading">
        <h2 id="process-summary-title">Process summary</h2>
        <span class="process-status-badge <?= esc($statusClass, 'attr') ?>"><?= esc($statusLabel) ?></span>
    </div>

    <dl class="process-details-grid">
        <div>
            <dt>Sales order</dt>
            <dd><?= esc($row['so_number'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Customer</dt>
            <dd><?= esc($row['customer_name'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Batch quantity</dt>
            <dd><?= esc($row['batch_qty'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Machine</dt>
            <dd><?= esc(trim(($row['machine_code'] ?? '') . ' ' . ($row['machine_name'] ?? '')) ?: $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Sector</dt>
            <dd><?= esc($row['sector_name'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Activity</dt>
            <dd><?= esc($row['activity_name'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Supervisor</dt>
            <dd><?= esc($row['supervisor_name'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Started (MYT)</dt>
            <dd><?= esc(kinta_local_time($row['started_at'] ?? null, $notRecorded)) ?></dd>
        </div>
        <div>
            <dt>Ended (MYT)</dt>
            <dd><?= esc(kinta_local_time($row['ended_at'] ?? null, $notRecorded)) ?></dd>
        </div>
        <div>
            <dt>Quantity passed</dt>
            <dd><?= esc($row['qty_passed'] ?? $notRecorded) ?></dd>
        </div>
        <div>
            <dt>Quantity rejected</dt>
            <dd><?= esc($row['qty_rejected'] ?? $notRecorded) ?></dd>
        </div>
        <div class="process-details-wide">
            <dt>Remarks</dt>
            <dd class="process-details-remarks"><?= ($row['remarks'] ?? '') !== '' ? nl2br(esc($row['remarks'])) : $notRecorded ?></dd>
        </div>
    </dl>
</section>

<?php foreach ($sections as $key => $section): ?>
    <?php $entries = $row[$key] ?? []; ?>
    <section class="process-history-section" aria-labelledby="process-<?= esc($key, 'attr') ?>-title">
        <h2 id="process-<?= esc($key, 'attr') ?>-title"><?= esc($section['title']) ?></h2>

        <?php if ($entries === []): ?>
            <p class="muted"><?= esc($section['empty']) ?></p>
        <?php else: ?>
            <div class="process-history-scroll">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($section['columns'] as $label): ?>
                                <th scope="col"><?= esc($label) ?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <?php foreach ($section['columns'] as $field => $label): ?>
                                    <?php
                                        $value = $entry[$field] ?? null;
                                        if (in_array($field, $timeFields, true)) {
                                            $emptyTime = $field === 'removed_at' || $field === 'ended_at' ? 'Current' : $notRecorded;
                                            $displayValue = $value ? kinta_local_time($value, $emptyTime) : $emptyTime;
                                        } else {
                                            $displayValue = $value === null || $value === '' ? $notRecorded : (string) $value;
                                        }
                                    ?>
                                    <td><?= esc($displayValue) ?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </section>
<?php endforeach ?>

<?php
$content = ob_get_clean();
echo view('admin/shell', ['title' => $title, 'content' => $content]);
?>

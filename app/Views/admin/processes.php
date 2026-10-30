<?php ob_start(); ?>
<section>
    <p><?= (int) $total ?> process record(s)</p>

    <div class="operation-report-table-scroll master-data-table-scroll">
        <table
            id="operation-reports-table"
            class="master-data-table"
            data-master-data-table
            data-master-module="operation-reports"
        >
            <thead>
                <tr>
                    <th>Process ID</th>
                    <th>S/O</th>
                    <th>Machine</th>
                    <th>Sector / activity</th>
                    <th>Supervisor</th>
                    <th>Status</th>
                    <th>Start / end (MYT)</th>
                    <th>Passed / rejected</th>
                    <th>Started date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $status = strtolower(trim((string) ($row['status'] ?? '')));
                        $statusLabel = $status === '' ? 'Unknown' : ucwords(str_replace(['_', '-'], ' ', $status));
                        $statusClass = match ($status) {
                            'completed' => 'is-complete',
                            'running', 'in_progress' => 'is-running',
                            'cancelled', 'failed' => 'is-stopped',
                            default => 'is-neutral',
                        };
                        $startedDate = '-';
                        if (! empty($row['started_at'])) {
                            try {
                                $startedDate = (new DateTimeImmutable((string) $row['started_at'], new DateTimeZone('UTC')))
                                    ->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'))
                                    ->format('Y-m-d');
                            } catch (Throwable) {
                                $startedDate = '-';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= esc($row['so_number'] ?? '-') ?></td>
                        <td><?= esc($row['machine_name'] ?? '-') ?></td>
                        <td><?= esc(($row['sector_name'] ?? '-') . ' / ' . ($row['activity_name'] ?? '-')) ?></td>
                        <td><?= esc($row['supervisor_name'] ?? '-') ?></td>
                        <td>
                            <span class="process-status-badge <?= esc($statusClass, 'attr') ?>">
                                <?= esc($statusLabel) ?>
                            </span>
                        </td>
                        <td>
                            <?= esc(kinta_local_time($row['started_at'] ?? null, '-')) ?><br>
                            <?= esc(kinta_local_time($row['ended_at'] ?? null, '-')) ?>
                        </td>
                        <td><?= esc($row['qty_passed'] ?? '-') ?> / <?= esc($row['qty_rejected'] ?? '-') ?></td>
                        <td data-search="<?= esc($startedDate, 'attr') ?>"><?= esc($startedDate) ?></td>
                        <td>
                            <a
                                class="view-record"
                                href="<?= site_url('admin/operations/' . (int) $row['id']) ?>"
                                aria-label="View process record <?= (int) $row['id'] ?>"
                                title="View process record"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2" />
                                    <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$content = ob_get_clean();
echo view('admin/shell', [
    'title' => $title,
    'content' => $content,
    'masterDataModule' => 'operation-reports',
]);
?>

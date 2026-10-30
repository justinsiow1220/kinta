<?php ob_start(); ?>
<div class="cards dashboard-cards">
    <a href="<?= site_url('admin/operations') ?>">
        <strong><?= (int) $counts['processes'] ?></strong><br>
        Process records
    </a>
    <a href="<?= site_url('admin/workforce') ?>">
        <strong><?= (int) $counts['workforce'] ?></strong><br>
        Active workforce
    </a>
    <a href="<?= site_url('admin/machines') ?>">
        <strong><?= (int) $counts['machines'] ?></strong><br>
        Active machines
    </a>
    <a href="<?= site_url('admin/sectors') ?>">
        <strong><?= (int) $counts['sectors'] ?></strong><br>
        Active sectors
    </a>
    <a href="<?= site_url('admin/activities') ?>">
        <strong><?= (int) $counts['activities'] ?></strong><br>
        Active activities
    </a>
    <a href="<?= site_url('admin/sales-orders') ?>">
        <strong><?= (int) $counts['sales_orders'] ?></strong><br>
        Active sales orders
    </a>
    <a href="<?= site_url('admin/problems') ?>">
        <strong><?= (int) $counts['problems'] ?></strong><br>
        Active problems
    </a>
</div>

<div class="dashboard-chart-grid">
    <section class="dashboard-chart-card">
        <h2>Monthly Operations</h2>
        <div class="dashboard-chart-canvas">
            <canvas id="monthly-operations-chart" role="img" aria-label="Number of operations by month for the most recent 12 months"></canvas>
        </div>
    </section>

    <section class="dashboard-chart-card">
        <h2>Operation Status Distribution</h2>
        <?php if ($operationStatuses['labels'] === []): ?>
            <p class="muted dashboard-chart-empty">No non-pending operation records are available to chart.</p>
        <?php else: ?>
            <div class="dashboard-chart-canvas">
                <canvas id="operation-status-chart" role="img" aria-label="Distribution of operation records by status"></canvas>
            </div>
        <?php endif ?>
    </section>
</div>

<section class="dashboard-section">
    <button
        class="dashboard-disclosure"
        type="button"
        data-dashboard-toggle
        aria-expanded="false"
        aria-controls="recent-process-records"
    >
        <span>Recent process records</span>
        <span class="dashboard-disclosure-arrow" aria-hidden="true">&#9662;</span>
    </button>

    <div id="recent-process-records" class="dashboard-section-content" hidden>
        <?php if ($recent === []): ?>
            <p class="muted">No process records have been synchronized yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>S/O</th>
                        <th>Machine / activity</th>
                        <th>Supervisor</th>
                        <th>Status</th>
                        <th>Started (MYT)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= esc($row['so_number'] ?? '-') ?></td>
                            <td><?= esc($row['machine_name'] . ' / ' . $row['activity_name']) ?></td>
                            <td><?= esc($row['supervisor_name']) ?></td>
                            <td><?= esc($row['status']) ?></td>
                            <td><?= esc(kinta_local_time($row['started_at'] ?? null, '-')) ?></td>
                            <td>
                                <a
                                    class="view-record"
                                    href="<?= site_url('admin/operations/' . $row['id']) ?>"
                                    aria-label="View process record"
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
        <?php endif ?>
    </div>
</section>

<section class="dashboard-section">
    <button
        class="dashboard-disclosure"
        type="button"
        data-dashboard-toggle
        aria-expanded="false"
        aria-controls="recent-master-data"
    >
        <span>Recently added master data and workforce</span>
        <span class="dashboard-disclosure-arrow" aria-hidden="true">&#9662;</span>
    </button>

    <div id="recent-master-data" class="dashboard-section-content" hidden>
        <?php if ($latestMaster === []): ?>
            <p class="muted">No master data or workforce has been added yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Record</th>
                        <th>Status</th>
                        <th>Added (MYT)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latestMaster as $row): ?>
                        <tr>
                            <td><?= esc($row['category']) ?></td>
                            <td><?= esc($row['label']) ?></td>
                            <td><?= esc($row['status']) ?></td>
                            <td><?= esc(kinta_local_time($row['created_at'] ?? null, '-')) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</section>

<script type="application/json" id="dashboard-chart-data"><?= json_encode([
    'monthlyOperations' => $monthlyOperations,
    'operationStatuses' => $operationStatuses,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= base_url('assets/vendor/chart.umd.min.js') ?>"></script>
<script>
    (() => {
        const dataElement = document.getElementById('dashboard-chart-data');
        if (!dataElement || !window.Chart) return;

        const chartData = JSON.parse(dataElement.textContent);
        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--kinta-primary').trim() || '#087cc1';
        const monthlyCanvas = document.getElementById('monthly-operations-chart');

        if (monthlyCanvas) {
            new window.Chart(monthlyCanvas, {
                type: 'line',
                data: {
                    labels: chartData.monthlyOperations.labels,
                    datasets: [{
                        label: 'Operations',
                        data: chartData.monthlyOperations.values,
                        borderColor: primaryColor,
                        backgroundColor: 'rgba(8, 124, 193, 0.12)',
                        pointBackgroundColor: primaryColor,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        tension: 0.25,
                        fill: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true },
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Month' },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            title: { display: true, text: 'Number of operations' },
                        },
                    },
                },
            });
        }

        const statusCanvas = document.getElementById('operation-status-chart');
        if (statusCanvas && chartData.operationStatuses.labels.length > 0) {
            const statusColors = {
                completed: '#16a34a',
                'in progress': '#facc15',
            };
            const rawStatusLabels = chartData.operationStatuses.labels.map((status) => String(status).trim());
            const statusLabels = rawStatusLabels;
            const backgroundColors = statusLabels.map((status) => statusColors[status.toLowerCase()]);
            const statusValues = chartData.operationStatuses.values;
            const total = statusValues.reduce((sum, value) => sum + value, 0);

            new window.Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: backgroundColors,
                        borderColor: '#ffffff',
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                generateLabels(chart) {
                                    return chart.data.labels.map((label, index) => ({
                                        text: `${label} (${statusValues[index]})`,
                                        fillStyle: backgroundColors[index],
                                        strokeStyle: '#ffffff',
                                        lineWidth: 2,
                                        hidden: false,
                                        index,
                                        datasetIndex: 0,
                                    }));
                                },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    const count = Number(context.raw) || 0;
                                    const percentage = total === 0 ? 0 : (count / total * 100);
                                    return `${context.label}: ${count} (${percentage.toFixed(1)}%)`;
                                },
                            },
                        },
                    },
                },
            });
        }
    })();

    document.querySelectorAll('[data-dashboard-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const content = document.getElementById(button.getAttribute('aria-controls'));
            if (!content) return;

            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            content.hidden = expanded;
        });
    });
</script>

<?php
$content = ob_get_clean();
echo view('admin/shell', ['title' => $title, 'content' => $content]);
?>

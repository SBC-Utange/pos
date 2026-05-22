<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_role('admin');
$currentUser = current_user();

$settings = get_settings($mysqli);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$weekStart = date('Y-m-d', strtotime('-6 days'));
$prevMonthStart = date('Y-m-01', strtotime('first day of last month'));
$prevMonthEnd = date('Y-m-t', strtotime('last month'));

$todayStats = [
    'count' => 0,
    'net_amount' => 0.0,
    'gross_amount' => 0.0,
    'discount' => 0.0,
];
$monthStats = [
    'count' => 0,
    'net_amount' => 0.0,
    'gross_amount' => 0.0,
    'discount' => 0.0,
];
$weekStats = [
    'count' => 0,
    'net_amount' => 0.0,
];
$prevMonthStats = [
    'count' => 0,
    'net_amount' => 0.0,
];
$recentSales = [];
$paymentBreakdown = [];
$topServices = [];

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count,
                                 COALESCE(SUM(total_amount), 0) AS net_amount,
                                 COALESCE(SUM(quantity * unit_price), 0) AS gross_amount,
                                 COALESCE(SUM(discount_amount), 0) AS discount_amount
                          FROM sales
                          WHERE sale_date = ?');
$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $todayStats['count'] = (int) $row['sale_count'];
    $todayStats['net_amount'] = (float) $row['net_amount'];
    $todayStats['gross_amount'] = (float) $row['gross_amount'];
    $todayStats['discount'] = (float) $row['discount_amount'];
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count,
                                 COALESCE(SUM(total_amount), 0) AS net_amount,
                                 COALESCE(SUM(quantity * unit_price), 0) AS gross_amount,
                                 COALESCE(SUM(discount_amount), 0) AS discount_amount
                          FROM sales
                          WHERE sale_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $monthStart, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $monthStats['count'] = (int) $row['sale_count'];
    $monthStats['net_amount'] = (float) $row['net_amount'];
    $monthStats['gross_amount'] = (float) $row['gross_amount'];
    $monthStats['discount'] = (float) $row['discount_amount'];
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount
                          FROM sales
                          WHERE sale_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $weekStart, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $weekStats['count'] = (int) $row['sale_count'];
    $weekStats['net_amount'] = (float) $row['net_amount'];
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount
                          FROM sales
                          WHERE sale_date BETWEEN ? AND ?');
$stmt->bind_param('ss', $prevMonthStart, $prevMonthEnd);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $prevMonthStats['count'] = (int) $row['sale_count'];
    $prevMonthStats['net_amount'] = (float) $row['net_amount'];
}
$stmt->close();

$paymentSql = 'SELECT payment_method, COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount
               FROM sales
               WHERE sale_date = ?
               GROUP BY payment_method
               ORDER BY net_amount DESC';
$stmt = $mysqli->prepare($paymentSql);
$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();
$paymentBreakdown = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$topServicesSql = "SELECT CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), sv.name) AS service_name,
                          COUNT(s.id) AS sale_count,
                          COALESCE(SUM(s.total_amount), 0) AS net_amount
                   FROM sales s
                   INNER JOIN services sv ON sv.id = s.service_id
                   LEFT JOIN services p ON p.id = sv.parent_id
                   WHERE s.sale_date BETWEEN ? AND ?
                   GROUP BY sv.id, sv.name, p.name
                   ORDER BY net_amount DESC
                   LIMIT 5";
$stmt = $mysqli->prepare($topServicesSql);
$stmt->bind_param('ss', $monthStart, $today);
$stmt->execute();
$result = $stmt->get_result();
$topServices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sql = "SELECT s.sale_date,
               s.customer_name,
               CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), sv.name) AS service_name,
               COALESCE(up.full_name, u.username, 'Unassigned') AS recorded_by,
               s.quantity,
               s.discount_amount,
               s.total_amount,
               s.payment_method
        FROM sales s
        INNER JOIN services sv ON sv.id = s.service_id
        LEFT JOIN services p ON p.id = sv.parent_id
        LEFT JOIN users u ON u.id = s.recorded_by_user_id
        LEFT JOIN user_profiles up ON up.user_id = u.id
        ORDER BY s.sale_date DESC, s.id DESC
        LIMIT 10";
$result = $mysqli->query($sql);
if ($result) {
    $recentSales = $result->fetch_all(MYSQLI_ASSOC);
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-speedometer2"></i></span>
        <div>
            <p class="page-kicker">Overview</p>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Track daily and monthly performance at a glance.</p>
        </div>
    </div>
    <div class="page-actions">
        <a href="sale_create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Record Sale</a>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Today Net (<?= e($today) ?>)</div>
                <div class="metric-value"><?= format_money($todayStats['net_amount'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted">
                    <?= e((string) $todayStats['count']) ?> sales | Discount <?= format_money($todayStats['discount'], $settings['currency_symbol']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Month Net (<?= e($monthStart) ?> to <?= e($today) ?>)</div>
                <div class="metric-value"><?= format_money($monthStats['net_amount'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted">
                    <?= e((string) $monthStats['count']) ?> sales | Discount <?= format_money($monthStats['discount'], $settings['currency_symbol']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Monthly Gross vs Discount</div>
                <div class="metric-value"><?= format_money($monthStats['gross_amount'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted">Gross Amount</div>
                <div class="progress mt-2" role="progressbar" aria-label="Discount Ratio">
                    <?php $discountRate = $monthStats['gross_amount'] > 0 ? min(100, ($monthStats['discount'] / $monthStats['gross_amount']) * 100) : 0; ?>
                    <div class="progress-bar bg-warning" style="width: <?= e(number_format($discountRate, 1)) ?>%"></div>
                </div>
                <div class="small text-muted mt-1">Discount rate: <?= e(number_format($discountRate, 1)) ?>%</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Performance Snapshot</strong>
                <span class="badge text-bg-primary">Live</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Last 7 Days</span>
                    <span><?= format_money($weekStats['net_amount'], $settings['currency_symbol']) ?> (<?= e((string) $weekStats['count']) ?> sales)</span>
                </div>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span class="text-muted">Previous Month</span>
                    <span><?= format_money($prevMonthStats['net_amount'], $settings['currency_symbol']) ?> (<?= e((string) $prevMonthStats['count']) ?> sales)</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Current Month</span>
                    <span><?= format_money($monthStats['net_amount'], $settings['currency_symbol']) ?> (<?= e((string) $monthStats['count']) ?> sales)</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><strong>Today's Payment Mix</strong></div>
            <div class="card-body">
                <?php if ($paymentBreakdown === []): ?>
                    <p class="text-muted mb-0">No payment activity today.</p>
                <?php else: ?>
                    <?php $todayTotal = max($todayStats['net_amount'], 0.01); ?>
                    <?php foreach ($paymentBreakdown as $pay): ?>
                        <?php $share = ((float) $pay['net_amount'] / $todayTotal) * 100; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?= e($pay['payment_method']) ?></span>
                                <span><?= format_money((float) $pay['net_amount'], $settings['currency_symbol']) ?> (<?= e(number_format($share, 1)) ?>%)</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" style="width: <?= e(number_format($share, 1)) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header"><strong>Top Services This Month</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Service</th>
                        <th class="text-end">Sales</th>
                        <th class="text-end">Net</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($topServices === []): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No service data yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topServices as $service): ?>
                            <tr>
                                <td><?= e($service['service_name']) ?></td>
                                <td class="text-end"><?= e((string) $service['sale_count']) ?></td>
                                <td class="text-end"><?= format_money((float) $service['net_amount'], $settings['currency_symbol']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header"><strong>Recent Sales</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Recorded By</th>
                        <th>Qty</th>
                        <th class="text-end">Discount</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentSales === []): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No sales recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?= e($sale['sale_date']) ?></td>
                                <td><?= e($sale['customer_name'] ?: '-') ?></td>
                                <td><?= e($sale['service_name']) ?></td>
                                <td><?= e($sale['recorded_by']) ?></td>
                                <td><?= e((string) $sale['quantity']) ?></td>
                                <td class="text-end"><?= format_money((float) $sale['discount_amount'], $settings['currency_symbol']) ?></td>
                                <td><?= e($sale['payment_method']) ?></td>
                                <td class="text-end"><?= format_money((float) $sale['total_amount'], $settings['currency_symbol']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

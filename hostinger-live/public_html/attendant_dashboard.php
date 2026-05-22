<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_role('attendant');
$currentUser = current_user();
$settings = get_settings($mysqli);

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-6 days'));

$profile = [
    'full_name' => $currentUser['full_name'] ?? $currentUser['username'],
    'employment_type' => 'Full-time',
    'salary_amount' => '0.00',
    'salary_cycle' => 'Monthly',
    'overtime_rate' => '0.00',
    'shift_start' => '',
    'shift_end' => '',
    'work_days' => '',
    'off_days' => '',
    'notes' => '',
];

$stmt = $mysqli->prepare('SELECT full_name, employment_type, salary_amount, salary_cycle, overtime_rate, shift_start, shift_end, work_days, off_days, notes
                          FROM user_profiles
                          WHERE user_id = ?
                          LIMIT 1');
$stmt->bind_param('i', $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
if ($row) {
    foreach ($profile as $key => $value) {
        $profile[$key] = (string) ($row[$key] ?? $value);
    }
}

$todayStats = ['count' => 0, 'net' => 0.0, 'discount' => 0.0];
$weekStats = ['count' => 0, 'net' => 0.0];
$paymentRows = [];
$recentSales = [];
$topServices = [];
$currentUserId = (int) $currentUser['id'];

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount, COALESCE(SUM(discount_amount), 0) AS discount_amount
                          FROM sales
                          WHERE sale_date = ? AND recorded_by_user_id = ?');
$stmt->bind_param('si', $today, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
if ($stats = $result->fetch_assoc()) {
    $todayStats['count'] = (int) $stats['sale_count'];
    $todayStats['net'] = (float) $stats['net_amount'];
    $todayStats['discount'] = (float) $stats['discount_amount'];
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount
                          FROM sales
                          WHERE sale_date BETWEEN ? AND ? AND recorded_by_user_id = ?');
$stmt->bind_param('ssi', $weekStart, $today, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
if ($stats = $result->fetch_assoc()) {
    $weekStats['count'] = (int) $stats['sale_count'];
    $weekStats['net'] = (float) $stats['net_amount'];
}
$stmt->close();

$stmt = $mysqli->prepare('SELECT payment_method, COUNT(*) AS sale_count, COALESCE(SUM(total_amount), 0) AS net_amount
                          FROM sales
                          WHERE sale_date = ? AND recorded_by_user_id = ?
                          GROUP BY payment_method
                          ORDER BY net_amount DESC');
$stmt->bind_param('si', $today, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$paymentRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sql = "SELECT CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), sv.name) AS service_name,
               COUNT(s.id) AS sale_count,
               COALESCE(SUM(s.total_amount), 0) AS net_amount
        FROM sales s
        INNER JOIN services sv ON sv.id = s.service_id
        LEFT JOIN services p ON p.id = sv.parent_id
                   WHERE s.sale_date BETWEEN ? AND ?
                     AND s.recorded_by_user_id = ?
                   GROUP BY sv.id, sv.name, p.name
                   ORDER BY net_amount DESC
                   LIMIT 5";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ssi', $weekStart, $today, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$topServices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sql = "SELECT s.sale_date,
               s.customer_name,
               CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), sv.name) AS service_name,
               s.quantity,
               s.discount_amount,
               s.total_amount,
               s.payment_method
        FROM sales s
        INNER JOIN services sv ON sv.id = s.service_id
        LEFT JOIN services p ON p.id = sv.parent_id
        WHERE s.recorded_by_user_id = ?
        ORDER BY s.sale_date DESC, s.id DESC
        LIMIT 10";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
if ($result instanceof mysqli_result) {
    $recentSales = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-person-workspace"></i></span>
        <div>
            <p class="page-kicker">Attendant Panel</p>
            <h1 class="page-title">Welcome, <?= e($profile['full_name']) ?></h1>
            <p class="page-subtitle">Your personal shift performance, earnings details, and quick actions.</p>
        </div>
    </div>
    <div class="page-actions">
        <a href="sale_create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Sale</a>
        <a href="profile.php" class="btn btn-outline-secondary"><i class="bi bi-person-badge me-1"></i>My Profile</a>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card metric-card h-100">
            <div class="card-body">
                <div class="text-muted small">Today's Net Sales</div>
                <div class="metric-value"><?= format_money($todayStats['net'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted"><?= e((string) $todayStats['count']) ?> transactions</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card metric-card h-100">
            <div class="card-body">
                <div class="text-muted small">Today's Discounts</div>
                <div class="metric-value"><?= format_money($todayStats['discount'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted">Monitor discount usage per shift</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card metric-card h-100">
            <div class="card-body">
                <div class="text-muted small">Last 7 Days</div>
                <div class="metric-value"><?= format_money($weekStats['net'], $settings['currency_symbol']) ?></div>
                <div class="small text-muted"><?= e((string) $weekStats['count']) ?> transactions</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><strong>My Schedule & Wage</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Employment</span><span><?= e($profile['employment_type']) ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Salary</span><span><?= format_money((float) $profile['salary_amount'], $settings['currency_symbol']) ?> / <?= e($profile['salary_cycle']) ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Overtime Rate</span><span><?= format_money((float) $profile['overtime_rate'], $settings['currency_symbol']) ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Shift</span><span><?= e($profile['shift_start'] !== '' ? $profile['shift_start'] : '--:--') ?> - <?= e($profile['shift_end'] !== '' ? $profile['shift_end'] : '--:--') ?></span></div>
                <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Work Days</span><span><?= e($profile['work_days'] !== '' ? $profile['work_days'] : '-') ?></span></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Off Days</span><span><?= e($profile['off_days'] !== '' ? $profile['off_days'] : '-') ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><strong>Today's Payment Mix</strong></div>
            <div class="card-body">
                <?php if ($paymentRows === []): ?>
                    <p class="text-muted mb-0">No payment activity today.</p>
                <?php else: ?>
                    <?php $totalToday = max($todayStats['net'], 0.01); ?>
                    <?php foreach ($paymentRows as $pay): ?>
                        <?php $share = ((float) $pay['net_amount'] / $totalToday) * 100; ?>
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

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><strong>Top Services (Last 7 Days)</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Service</th>
                        <th class="text-end">Count</th>
                        <th class="text-end">Net</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($topServices === []): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No sales data.</td></tr>
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
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><strong>Recent Sales</strong></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Qty</th>
                        <th class="text-end">Discount</th>
                        <th>Method</th>
                        <th class="text-end">Net</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentSales === []): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No recent sales.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><?= e($sale['sale_date']) ?></td>
                                <td><?= e($sale['customer_name'] ?: '-') ?></td>
                                <td><?= e($sale['service_name']) ?></td>
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

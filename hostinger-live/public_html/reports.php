<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_role('admin');
$currentUser = current_user();

$settings = get_settings($mysqli);

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));

$fromObj = DateTime::createFromFormat('Y-m-d', $from);
if (!$fromObj || $fromObj->format('Y-m-d') !== $from) {
    $from = date('Y-m-01');
}

$toObj = DateTime::createFromFormat('Y-m-d', $to);
if (!$toObj || $toObj->format('Y-m-d') !== $to) {
    $to = date('Y-m-d');
}

$sql = 'SELECT sv.id,
               sv.name AS service_name,
               p.id AS parent_service_id,
               p.name AS parent_service_name,
               COUNT(s.id) AS sales_count,
               COALESCE(SUM(s.quantity), 0) AS total_quantity,
               COALESCE(SUM(s.discount_amount), 0) AS total_discount,
               COALESCE(SUM(s.total_amount), 0) AS total_amount
        FROM services sv
        LEFT JOIN services p ON p.id = sv.parent_id
        LEFT JOIN sales s
            ON s.service_id = sv.id
           AND s.sale_date BETWEEN ? AND ?
        GROUP BY sv.id, sv.name, p.id, p.name
        ORDER BY COALESCE(p.name, sv.name), sv.parent_id IS NOT NULL, sv.name';
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$paymentSql = 'SELECT payment_method, COUNT(*) AS sales_count, COALESCE(SUM(discount_amount), 0) AS total_discount, COALESCE(SUM(total_amount), 0) AS total_amount
               FROM sales
               WHERE sale_date BETWEEN ? AND ?
               GROUP BY payment_method
               ORDER BY total_amount DESC';
$stmt = $mysqli->prepare($paymentSql);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$paymentResult = $stmt->get_result();
$paymentRows = $paymentResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$attendantSql = 'SELECT COALESCE(p.full_name, u.username) AS attendant_name,
                        COUNT(s.id) AS sales_count,
                        COALESCE(SUM(s.discount_amount), 0) AS total_discount,
                        COALESCE(SUM(s.total_amount), 0) AS total_amount
                 FROM sales s
                 INNER JOIN users u ON u.id = s.recorded_by_user_id
                 LEFT JOIN user_profiles p ON p.user_id = u.id
                 WHERE s.sale_date BETWEEN ? AND ?
                 GROUP BY u.id, attendant_name
                 ORDER BY total_amount DESC';
$stmt = $mysqli->prepare($attendantSql);
$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$attendantResult = $stmt->get_result();
$attendantRows = $attendantResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grouped = [];
$grandQuantity = 0;
$grandDiscount = 0.0;
$grandAmount = 0.0;
$grandSalesCount = 0;

foreach ($rows as $row) {
    $parentKey = $row['parent_service_id'] !== null ? (string) $row['parent_service_id'] : 'root_' . $row['id'];
    $parentName = $row['parent_service_name'] ?: $row['service_name'];

    if (!isset($grouped[$parentKey])) {
        $grouped[$parentKey] = [
            'parent_name' => $parentName,
            'sales_count' => 0,
            'total_quantity' => 0,
            'total_discount' => 0.0,
            'total_amount' => 0.0,
            'children' => [],
        ];
    }

    $salesCount = (int) $row['sales_count'];
    $totalQuantity = (int) $row['total_quantity'];
    $totalDiscount = (float) $row['total_discount'];
    $totalAmount = (float) $row['total_amount'];

    if ($row['parent_service_id'] !== null) {
        $grouped[$parentKey]['children'][] = [
            'service_name' => $row['service_name'],
            'sales_count' => $salesCount,
            'total_quantity' => $totalQuantity,
            'total_discount' => $totalDiscount,
            'total_amount' => $totalAmount,
        ];
    }

    $grouped[$parentKey]['sales_count'] += $salesCount;
    $grouped[$parentKey]['total_quantity'] += $totalQuantity;
    $grouped[$parentKey]['total_discount'] += $totalDiscount;
    $grouped[$parentKey]['total_amount'] += $totalAmount;

    $grandSalesCount += $salesCount;
    $grandQuantity += $totalQuantity;
    $grandDiscount += $totalDiscount;
    $grandAmount += $totalAmount;
}

$paymentTotal = 0.0;
foreach ($paymentRows as $paymentRow) {
    $paymentTotal += (float) $paymentRow['total_amount'];
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');

    fputcsv($out, ['Service Group', 'Service', 'Sales Count', 'Total Quantity', 'Total Discount', 'Total Revenue']);
    foreach ($grouped as $group) {
        if ($group['children'] === []) {
            fputcsv($out, [$group['parent_name'], $group['parent_name'], $group['sales_count'], $group['total_quantity'], $group['total_discount'], $group['total_amount']]);
            continue;
        }

        fputcsv($out, [$group['parent_name'], '(Group Total)', $group['sales_count'], $group['total_quantity'], $group['total_discount'], $group['total_amount']]);
        foreach ($group['children'] as $child) {
            fputcsv($out, [$group['parent_name'], $child['service_name'], $child['sales_count'], $child['total_quantity'], $child['total_discount'], $child['total_amount']]);
        }
    }

    fputcsv($out, []);
    fputcsv($out, ['Payment Method', 'Sales Count', 'Total Discount', 'Total Revenue']);
    foreach ($paymentRows as $paymentRow) {
        fputcsv($out, [$paymentRow['payment_method'], $paymentRow['sales_count'], $paymentRow['total_discount'], $paymentRow['total_amount']]);
    }

    fclose($out);
    exit;
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-bar-chart-line"></i></span>
        <div>
            <p class="page-kicker">Analytics</p>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Analyze grouped services, discounts, and payment trends.</p>
        </div>
    </div>
    <div class="page-actions">
        <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
    </div>
</section>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-5">
                <label class="form-label">From</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
            </div>
            <div class="col-md-5">
                <label class="form-label">To</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Sales Entries</div>
                <div class="metric-value"><?= e((string) $grandSalesCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Quantity</div>
                <div class="metric-value"><?= e((string) $grandQuantity) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Discount</div>
                <div class="metric-value"><?= format_money($grandDiscount, $settings['currency_symbol']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Total Revenue</div>
                <div class="metric-value"><?= format_money($grandAmount, $settings['currency_symbol']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header">
        <strong>Service Performance (Parent / Child)</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Service</th>
                <th class="text-end">Sales Count</th>
                <th class="text-end">Total Qty</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Revenue</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($grouped === []): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No report data for selected range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($grouped as $group): ?>
                    <tr class="table-primary-subtle">
                        <td><strong><?= e($group['parent_name']) ?></strong></td>
                        <td class="text-end"><strong><?= e((string) $group['sales_count']) ?></strong></td>
                        <td class="text-end"><strong><?= e((string) $group['total_quantity']) ?></strong></td>
                        <td class="text-end"><strong><?= format_money((float) $group['total_discount'], $settings['currency_symbol']) ?></strong></td>
                        <td class="text-end"><strong><?= format_money((float) $group['total_amount'], $settings['currency_symbol']) ?></strong></td>
                    </tr>
                    <?php foreach ($group['children'] as $child): ?>
                        <tr>
                            <td><span class="text-body-tertiary me-1">-></span><?= e($child['service_name']) ?></td>
                            <td class="text-end"><?= e((string) $child['sales_count']) ?></td>
                            <td class="text-end"><?= e((string) $child['total_quantity']) ?></td>
                            <td class="text-end"><?= format_money((float) $child['total_discount'], $settings['currency_symbol']) ?></td>
                            <td class="text-end"><?= format_money((float) $child['total_amount'], $settings['currency_symbol']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <strong>Payment Method Summary</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Payment Method</th>
                <th class="text-end">Sales Count</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Revenue</th>
                <th class="text-end">Share</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($paymentRows === []): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No payment activity for selected range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($paymentRows as $paymentRow): ?>
                    <?php
                    $amount = (float) $paymentRow['total_amount'];
                    $share = $paymentTotal > 0 ? ($amount / $paymentTotal) * 100 : 0.0;
                    ?>
                    <tr>
                        <td><?= e($paymentRow['payment_method']) ?></td>
                        <td class="text-end"><?= e((string) $paymentRow['sales_count']) ?></td>
                        <td class="text-end"><?= format_money((float) $paymentRow['total_discount'], $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= format_money($amount, $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= e(number_format($share, 1)) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header">
        <strong>Attendant Performance</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Attendant</th>
                <th class="text-end">Sales Count</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Revenue</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($attendantRows === []): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No attendant-tracked sales in selected range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendantRows as $attendantRow): ?>
                    <tr>
                        <td><?= e($attendantRow['attendant_name']) ?></td>
                        <td class="text-end"><?= e((string) $attendantRow['sales_count']) ?></td>
                        <td class="text-end"><?= format_money((float) $attendantRow['total_discount'], $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= format_money((float) $attendantRow['total_amount'], $settings['currency_symbol']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

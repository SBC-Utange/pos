<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_login();
enforce_attendant_page_access(basename((string) ($_SERVER['PHP_SELF'] ?? 'sales.php')));
$currentUser = current_user();

$settings = get_settings($mysqli);
$services = fetch_services($mysqli);

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
$serviceIdRaw = trim((string) ($_GET['service_id'] ?? ''));
$serviceId = $serviceIdRaw !== '' ? filter_var($serviceIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;

$fromObj = DateTime::createFromFormat('Y-m-d', $from);
if (!$fromObj || $fromObj->format('Y-m-d') !== $from) {
    $from = date('Y-m-01');
}

$toObj = DateTime::createFromFormat('Y-m-d', $to);
if (!$toObj || $toObj->format('Y-m-d') !== $to) {
    $to = date('Y-m-d');
}

$params = [$from, $to];
$types = 'ss';
$serviceFilterSql = '';
if ($serviceId !== null && $serviceId !== false) {
    $serviceFilterSql = ' AND s.service_id = ?';
    $params[] = (int) $serviceId;
    $types .= 'i';
}

$sql = "SELECT s.id,
               s.sale_date,
               s.customer_name,
               CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), sv.name) AS service_name,
               COALESCE(up.full_name, u.username, 'Unassigned') AS recorded_by,
               s.quantity,
               s.unit_price,
               s.discount_amount,
               s.total_amount,
               s.payment_method
        FROM sales s
        INNER JOIN services sv ON sv.id = s.service_id
        LEFT JOIN services p ON p.id = sv.parent_id
        LEFT JOIN users u ON u.id = s.recorded_by_user_id
        LEFT JOIN user_profiles up ON up.user_id = u.id
        WHERE s.sale_date BETWEEN ? AND ?{$serviceFilterSql}
        ORDER BY s.sale_date DESC, s.id DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalAmount = 0.0;
$totalDiscount = 0.0;
foreach ($sales as $sale) {
    $totalAmount += (float) $sale['total_amount'];
    $totalDiscount += (float) $sale['discount_amount'];
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Date', 'Customer', 'Service', 'Recorded By', 'Quantity', 'Unit Price', 'Discount', 'Net Amount', 'Payment Method']);
    foreach ($sales as $sale) {
        fputcsv($out, [
            $sale['sale_date'],
            $sale['customer_name'] ?? '',
            $sale['service_name'],
            $sale['recorded_by'],
            $sale['quantity'],
            $sale['unit_price'],
            $sale['discount_amount'],
            $sale['total_amount'],
            $sale['payment_method'],
        ]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-receipt-cutoff"></i></span>
        <div>
            <p class="page-kicker">Transactions</p>
            <h1 class="page-title">Sales</h1>
            <p class="page-subtitle">Filter, review, and export all sales records.</p>
        </div>
    </div>
    <div class="page-actions">
        <a href="sale_create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Sale</a>
        <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&service_id=<?= e($serviceIdRaw) ?>&export=csv" class="btn btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
    </div>
</section>

<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">Sale recorded successfully.</div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Service</label>
                <select name="service_id" class="form-select">
                    <option value="">All services</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= e((string) $service['id']) ?>" <?= $serviceIdRaw === (string) $service['id'] ? 'selected' : '' ?>>
                            <?= e($service['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><strong><?= e((string) count($sales)) ?></strong> sales found</span>
        <span>
            <strong>Discount:</strong> <?= format_money($totalDiscount, $settings['currency_symbol']) ?>
            <span class="mx-2">|</span>
            <strong>Net Total:</strong> <?= format_money($totalAmount, $settings['currency_symbol']) ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Recorded By</th>
                <th>Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Net Total</th>
                <th>Method</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($sales === []): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No sales in selected range.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= e($sale['sale_date']) ?></td>
                        <td><?= e($sale['customer_name'] ?: '-') ?></td>
                        <td><?= e($sale['service_name']) ?></td>
                        <td><?= e($sale['recorded_by']) ?></td>
                        <td><?= e((string) $sale['quantity']) ?></td>
                        <td class="text-end"><?= format_money((float) $sale['unit_price'], $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= format_money((float) $sale['discount_amount'], $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= format_money((float) $sale['total_amount'], $settings['currency_symbol']) ?></td>
                        <td><?= e($sale['payment_method']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

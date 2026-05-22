<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_login();
enforce_attendant_page_access(basename((string) ($_SERVER['PHP_SELF'] ?? 'sale_create.php')));
$currentUser = current_user();

$settings = get_settings($mysqli);
$services = fetch_services($mysqli);

$paymentMethods = ['Cash', 'M-Pesa', 'Card', 'Bank Transfer', 'Credit'];
$errors = [];

$input = [
    'service_id' => '',
    'customer_name' => '',
    'quantity' => '1',
    'unit_price' => '',
    'discount_amount' => '0.00',
    'payment_method' => 'Cash',
    'sale_date' => date('Y-m-d'),
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $input['service_id'] = trim((string) ($_POST['service_id'] ?? ''));
    $input['customer_name'] = trim((string) ($_POST['customer_name'] ?? ''));
    $input['quantity'] = trim((string) ($_POST['quantity'] ?? '1'));
    $input['unit_price'] = trim((string) ($_POST['unit_price'] ?? ''));
    $input['discount_amount'] = trim((string) ($_POST['discount_amount'] ?? '0'));
    $input['payment_method'] = trim((string) ($_POST['payment_method'] ?? 'Cash'));
    $input['sale_date'] = trim((string) ($_POST['sale_date'] ?? date('Y-m-d')));
    $input['notes'] = trim((string) ($_POST['notes'] ?? ''));

    $serviceId = filter_var($input['service_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($input['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $unitPrice = filter_var($input['unit_price'], FILTER_VALIDATE_FLOAT);
    $discountAmount = filter_var($input['discount_amount'], FILTER_VALIDATE_FLOAT);

    if ($serviceId === false || get_service_by_id($mysqli, (int) $serviceId) === null) {
        $errors[] = 'Select a valid service.';
    }

    if ($quantity === false) {
        $errors[] = 'Quantity must be at least 1.';
    }

    if ($unitPrice === false || (float) $unitPrice < 0) {
        $errors[] = 'Unit price must be a valid non-negative number.';
    }

    if ($discountAmount === false || (float) $discountAmount < 0) {
        $errors[] = 'Discount must be a valid non-negative number.';
    }

    if (!in_array($input['payment_method'], $paymentMethods, true)) {
        $errors[] = 'Select a valid payment method.';
    }

    $saleDateObj = DateTime::createFromFormat('Y-m-d', $input['sale_date']);
    if (!$saleDateObj || $saleDateObj->format('Y-m-d') !== $input['sale_date']) {
        $errors[] = 'Sale date must be a valid date.';
    }

    if ($errors === []) {
        $qty = (int) $quantity;
        $price = (float) $unitPrice;
        $grossTotal = $qty * $price;
        $discount = (float) $discountAmount;
        if ($discount > $grossTotal) {
            $errors[] = 'Discount cannot exceed gross total.';
        }
    }

    if ($errors === []) {
        $qty = (int) $quantity;
        $price = (float) $unitPrice;
        $discount = (float) $discountAmount;
        $total = $qty * $price - $discount;
        $customerName = $input['customer_name'] !== '' ? $input['customer_name'] : null;
        $notes = $input['notes'] !== '' ? $input['notes'] : null;
        $saleDate = $saleDateObj->format('Y-m-d');

        $recordedByUserId = (int) $currentUser['id'];
        $stmt = $mysqli->prepare('INSERT INTO sales (service_id, recorded_by_user_id, customer_name, quantity, unit_price, discount_amount, total_amount, payment_method, sale_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param(
            'iisidddsss',
            $serviceId,
            $recordedByUserId,
            $customerName,
            $qty,
            $price,
            $discount,
            $total,
            $input['payment_method'],
            $saleDate,
            $notes
        );
        $stmt->execute();
        $stmt->close();

        redirect('sales.php?created=1');
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-cart-plus"></i></span>
        <div>
            <p class="page-kicker">Input</p>
            <h1 class="page-title">Record New Sale</h1>
            <p class="page-subtitle">Capture quantity, discounts, payment, and notes.</p>
        </div>
    </div>
    <div class="page-actions">
        <a href="sales.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Sales</a>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label">Service</label>
                <select name="service_id" id="service_id" class="form-select" required>
                    <option value="">Select service</option>
                    <?php foreach ($services as $service): ?>
                        <option
                            value="<?= e((string) $service['id']) ?>"
                            data-price="<?= e((string) $service['default_price']) ?>"
                            <?= $input['service_id'] === (string) $service['id'] ? 'selected' : '' ?>
                        >
                            <?= e($service['display_name']) ?> (Default: <?= format_money((float) $service['default_price'], $settings['currency_symbol']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Customer Name (Optional)</label>
                <input type="text" name="customer_name" class="form-control" maxlength="120" value="<?= e($input['customer_name']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Quantity</label>
                <input type="number" min="1" name="quantity" class="form-control" value="<?= e($input['quantity']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Unit Price</label>
                <input type="number" step="0.01" min="0" name="unit_price" id="unit_price" class="form-control" value="<?= e($input['unit_price']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-select" required>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= e($method) ?>" <?= $input['payment_method'] === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Sale Date</label>
                <input type="date" name="sale_date" class="form-control" value="<?= e($input['sale_date']) ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Discount Amount</label>
                <input type="number" step="0.01" min="0" name="discount_amount" class="form-control" value="<?= e($input['discount_amount']) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Notes (Optional)</label>
                <textarea name="notes" class="form-control" rows="3"><?= e($input['notes']) ?></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Sale</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        var service = document.getElementById('service_id');
        var unitPrice = document.getElementById('unit_price');
        if (!service || !unitPrice) {
            return;
        }

        service.addEventListener('change', function () {
            if (unitPrice.value !== '') {
                return;
            }
            var selected = service.options[service.selectedIndex];
            var price = selected ? selected.getAttribute('data-price') : '';
            if (price) {
                unitPrice.value = Number(price).toFixed(2);
            }
        });
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_login();
enforce_attendant_page_access(basename((string) ($_SERVER['PHP_SELF'] ?? 'services.php')));
$currentUser = current_user();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$settings = get_settings($mysqli);
$errors = [];

$editId = $isAdmin ? filter_var((string) ($_GET['edit'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
$editService = $editId ? get_service_by_id($mysqli, (int) $editId) : null;

$form = [
    'service_id' => $editService ? (string) $editService['id'] : '',
    'name' => $editService['name'] ?? '',
    'default_price' => $editService ? (string) $editService['default_price'] : '',
    'parent_id' => $editService && $editService['parent_id'] !== null ? (string) $editService['parent_id'] : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'save') {
        $serviceId = $isAdmin
            ? filter_var((string) ($_POST['service_id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $defaultPriceRaw = trim((string) ($_POST['default_price'] ?? ''));
        $defaultPrice = filter_var($defaultPriceRaw, FILTER_VALIDATE_FLOAT);
        $parentIdRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = $parentIdRaw === '' ? null : filter_var($parentIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $form = [
            'service_id' => $serviceId ? (string) $serviceId : '',
            'name' => $name,
            'default_price' => $defaultPriceRaw,
            'parent_id' => $parentIdRaw,
        ];

        if ($name === '') {
            $errors[] = 'Service name is required.';
        }

        if ($defaultPrice === false || (float) $defaultPrice < 0) {
            $errors[] = 'Default price must be a valid non-negative number.';
        }

        if ($parentIdRaw !== '' && $parentId === false) {
            $errors[] = 'Selected parent is invalid.';
        }

        if ($serviceId !== false && $serviceId !== null && $parentId !== null && (int) $serviceId === (int) $parentId) {
            $errors[] = 'A service cannot be its own parent.';
        }

        if ($parentId !== null) {
            $parentService = get_service_by_id($mysqli, (int) $parentId);
            if ($parentService === null) {
                $errors[] = 'Parent service not found.';
            } elseif ($parentService['parent_id'] !== null) {
                $errors[] = 'Parent service must be a top-level service.';
            }
        }

        if ($errors === []) {
            if ($serviceId !== false && $serviceId !== null) {
                $stmt = $mysqli->prepare('UPDATE services SET name = ?, parent_id = ?, default_price = ? WHERE id = ?');
                $stmt->bind_param('sidi', $name, $parentId, $defaultPrice, $serviceId);
                $stmt->execute();
                $stmt->close();
                redirect('services.php?updated=1');
            } else {
                $stmt = $mysqli->prepare('INSERT INTO services (name, parent_id, default_price, is_active) VALUES (?, ?, ?, 1)');
                $stmt->bind_param('sid', $name, $parentId, $defaultPrice);
                $stmt->execute();
                $stmt->close();
                redirect('services.php?created=1');
            }
        }
    }

    if ($action === 'toggle') {
        if ($isAdmin) {
            $id = filter_var((string) ($_POST['id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $isActive = (int) ($_POST['is_active'] ?? 0);
            if ($id !== false) {
                $newStatus = $isActive === 1 ? 0 : 1;
                $stmt = $mysqli->prepare('UPDATE services SET is_active = ? WHERE id = ?');
                $stmt->bind_param('ii', $newStatus, $id);
                $stmt->execute();
                $stmt->close();
                redirect('services.php?toggled=1');
            }
        } else {
            $errors[] = 'Not allowed.';
        }
    }

    if ($action === 'delete') {
        if ($isAdmin) {
            $id = filter_var((string) ($_POST['id'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $stmt = $mysqli->prepare('SELECT COUNT(*) AS linked_sales FROM sales WHERE service_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $linkedSales = 0;
                if ($row = $result->fetch_assoc()) {
                    $linkedSales = (int) $row['linked_sales'];
                }
                $stmt->close();

                if ($linkedSales > 0) {
                    $errors[] = 'Cannot delete service with existing sales records.';
                } else {
                    $stmt = $mysqli->prepare('UPDATE services SET parent_id = NULL WHERE parent_id = ?');
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $mysqli->prepare('DELETE FROM services WHERE id = ?');
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    redirect('services.php?deleted=1');
                }
            }
        } else {
            $errors[] = 'Not allowed.';
        }
    }
}

$parentOptions = fetch_service_parents($mysqli, $form['service_id'] !== '' ? (int) $form['service_id'] : null);

$sql = 'SELECT sv.id,
               sv.name,
               sv.parent_id,
               p.name AS parent_name,
               sv.default_price,
               sv.is_active,
               COUNT(s.id) AS sales_count,
               COALESCE(SUM(s.total_amount), 0) AS total_amount
        FROM services sv
        LEFT JOIN services p ON p.id = sv.parent_id
        LEFT JOIN sales s ON s.service_id = sv.id
        GROUP BY sv.id, sv.name, sv.parent_id, p.name, sv.default_price, sv.is_active
        ORDER BY COALESCE(p.name, sv.name), sv.parent_id IS NOT NULL, sv.name';
$result = $mysqli->query($sql);
$serviceRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-diagram-3"></i></span>
        <div>
            <p class="page-kicker">Catalog</p>
            <h1 class="page-title">Services Management</h1>
            <p class="page-subtitle">Manage parent-child service structure, pricing, and status.</p>
        </div>
    </div>
</section>

<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">Service created successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Service updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">Service deleted successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['toggled'])): ?>
    <div class="alert alert-success">Service status updated.</div>
<?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
    <div class="card-header">
        <strong><?= $isAdmin && $form['service_id'] !== '' ? 'Edit Service' : 'Add Service' ?></strong>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="service_id" value="<?= e($form['service_id']) ?>">
            <div class="col-lg-4">
                <label class="form-label">Service Name</label>
                <input type="text" name="name" class="form-control" maxlength="120" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Parent Service</label>
                <select name="parent_id" class="form-select">
                    <option value="">Top-level service</option>
                    <?php foreach ($parentOptions as $parent): ?>
                        <option value="<?= e((string) $parent['id']) ?>" <?= $form['parent_id'] === (string) $parent['id'] ? 'selected' : '' ?>>
                            <?= e($parent['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Default Price</label>
                <input type="number" step="0.01" min="0" name="default_price" class="form-control" value="<?= e($form['default_price']) ?>" required>
            </div>
            <div class="col-lg-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100"><?= $isAdmin && $form['service_id'] !== '' ? 'Update' : 'Save' ?></button>
                <?php if ($isAdmin && $form['service_id'] !== ''): ?>
                    <a href="services.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>Service</th>
                <th>Group</th>
                <th class="text-end">Default Price</th>
                <th class="text-end">Sales Count</th>
                <th class="text-end">Revenue</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($serviceRows === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No services found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($serviceRows as $service): ?>
                    <?php $isChild = $service['parent_id'] !== null; ?>
                    <tr>
                        <td>
                            <?= $isChild ? '<span class="text-body-tertiary me-1">-></span>' : '' ?>
                            <?= e($service['name']) ?>
                        </td>
                        <td><?= e($service['parent_name'] ?: 'Parent') ?></td>
                        <td class="text-end"><?= format_money((float) $service['default_price'], $settings['currency_symbol']) ?></td>
                        <td class="text-end"><?= e((string) $service['sales_count']) ?></td>
                        <td class="text-end"><?= format_money((float) $service['total_amount'], $settings['currency_symbol']) ?></td>
                        <td>
                            <?php if ((int) $service['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($isAdmin): ?>
                                <a href="services.php?edit=<?= e((string) $service['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= e((string) $service['id']) ?>">
                                    <input type="hidden" name="is_active" value="<?= e((string) $service['is_active']) ?>">
                                    <button type="submit" class="btn btn-sm <?= (int) $service['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                        <?= (int) $service['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this service?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e((string) $service['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

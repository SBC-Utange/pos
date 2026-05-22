<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

require_login();
enforce_attendant_page_access(basename((string) ($_SERVER['PHP_SELF'] ?? 'profile.php')));
$currentUser = current_user();
$settings = get_settings($mysqli);

$employmentTypes = ['Full-time', 'Part-time', 'Contract'];
$salaryCycles = ['Daily', 'Weekly', 'Monthly'];
$errors = [];

$profile = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'next_of_kin' => '',
    'next_of_kin_phone' => '',
    'hire_date' => '',
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

$stmt = $mysqli->prepare('SELECT full_name, email, phone, address, next_of_kin, next_of_kin_phone, hire_date, employment_type, salary_amount, salary_cycle, overtime_rate, shift_start, shift_end, work_days, off_days, notes
                          FROM user_profiles WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $currentUser['id']);
$stmt->execute();
$result = $stmt->get_result();
$dbProfile = $result->fetch_assoc();
$stmt->close();

if ($dbProfile) {
    foreach ($profile as $key => $value) {
        $profile[$key] = (string) ($dbProfile[$key] ?? $value);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    foreach ($profile as $key => $value) {
        $profile[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($profile['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($profile['employment_type'], $employmentTypes, true)) {
        $errors[] = 'Invalid employment type.';
    }
    if (!in_array($profile['salary_cycle'], $salaryCycles, true)) {
        $errors[] = 'Invalid salary cycle.';
    }

    $salaryAmount = filter_var($profile['salary_amount'], FILTER_VALIDATE_FLOAT);
    $overtimeRate = filter_var($profile['overtime_rate'], FILTER_VALIDATE_FLOAT);
    if ($salaryAmount === false || (float) $salaryAmount < 0) {
        $errors[] = 'Salary amount must be non-negative.';
    }
    if ($overtimeRate === false || (float) $overtimeRate < 0) {
        $errors[] = 'Overtime rate must be non-negative.';
    }

    if ($errors === []) {
        $stmt = $mysqli->prepare('INSERT INTO user_profiles
            (user_id, full_name, email, phone, address, next_of_kin, next_of_kin_phone, hire_date, employment_type, salary_amount, salary_cycle, overtime_rate, shift_start, shift_end, work_days, off_days, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                phone = VALUES(phone),
                address = VALUES(address),
                next_of_kin = VALUES(next_of_kin),
                next_of_kin_phone = VALUES(next_of_kin_phone),
                hire_date = VALUES(hire_date),
                employment_type = VALUES(employment_type),
                salary_amount = VALUES(salary_amount),
                salary_cycle = VALUES(salary_cycle),
                overtime_rate = VALUES(overtime_rate),
                shift_start = VALUES(shift_start),
                shift_end = VALUES(shift_end),
                work_days = VALUES(work_days),
                off_days = VALUES(off_days),
                notes = VALUES(notes)');

        $hireDate = $profile['hire_date'] !== '' ? $profile['hire_date'] : null;
        $shiftStart = $profile['shift_start'] !== '' ? $profile['shift_start'] : null;
        $shiftEnd = $profile['shift_end'] !== '' ? $profile['shift_end'] : null;
        $salaryAmountFloat = (float) $salaryAmount;
        $overtimeRateFloat = (float) $overtimeRate;

        $stmt->bind_param(
            'issssssssdsdsssss',
            $currentUser['id'],
            $profile['full_name'],
            $profile['email'],
            $profile['phone'],
            $profile['address'],
            $profile['next_of_kin'],
            $profile['next_of_kin_phone'],
            $hireDate,
            $profile['employment_type'],
            $salaryAmountFloat,
            $profile['salary_cycle'],
            $overtimeRateFloat,
            $shiftStart,
            $shiftEnd,
            $profile['work_days'],
            $profile['off_days'],
            $profile['notes']
        );
        $stmt->execute();
        $stmt->close();

        $_SESSION['auth_user']['full_name'] = $profile['full_name'];
        redirect('profile.php?saved=1');
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <div class="page-title-wrap">
        <span class="page-icon"><i class="bi bi-person-badge"></i></span>
        <div>
            <p class="page-kicker">Account</p>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Update personal details, schedule, and wage information.</p>
        </div>
    </div>
</section>

<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Profile updated successfully.</div>
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

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($profile['phone']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Next of Kin</label>
                <input type="text" name="next_of_kin" class="form-control" value="<?= e($profile['next_of_kin']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Kin Phone</label>
                <input type="text" name="next_of_kin_phone" class="form-control" value="<?= e($profile['next_of_kin_phone']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= e($profile['address']) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Hire Date</label>
                <input type="date" name="hire_date" class="form-control" value="<?= e($profile['hire_date']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Employment Type</label>
                <select name="employment_type" class="form-select">
                    <?php foreach ($employmentTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= $profile['employment_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Salary Amount</label>
                <input type="number" step="0.01" min="0" name="salary_amount" class="form-control" value="<?= e($profile['salary_amount']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Salary Cycle</label>
                <select name="salary_cycle" class="form-select">
                    <?php foreach ($salaryCycles as $cycle): ?>
                        <option value="<?= e($cycle) ?>" <?= $profile['salary_cycle'] === $cycle ? 'selected' : '' ?>><?= e($cycle) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Overtime Rate</label>
                <input type="number" step="0.01" min="0" name="overtime_rate" class="form-control" value="<?= e($profile['overtime_rate']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Shift Start</label>
                <input type="time" name="shift_start" class="form-control" value="<?= e($profile['shift_start']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Shift End</label>
                <input type="time" name="shift_end" class="form-control" value="<?= e($profile['shift_end']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Work Days</label>
                <input type="text" name="work_days" class="form-control" value="<?= e($profile['work_days']) ?>" placeholder="Mon-Sat">
            </div>
            <div class="col-md-6">
                <label class="form-label">Off Days</label>
                <input type="text" name="off_days" class="form-control" value="<?= e($profile['off_days']) ?>" placeholder="Sunday">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= e($profile['notes']) ?></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Profile</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

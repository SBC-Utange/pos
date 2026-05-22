<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    if (($user['role'] ?? '') === 'attendant') {
        redirect('attendant_dashboard.php');
    }
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_die();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } elseif (!login_user($mysqli, $username, $password)) {
        $error = 'Invalid credentials.';
    } else {
        $user = current_user();
        if (($user['role'] ?? '') === 'attendant') {
            redirect('attendant_dashboard.php');
        }
        redirect('index.php');
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Sign in</h1>
                    <p class="text-body-secondary small mb-4">Sales Record Manager</p>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" class="d-grid gap-3">
                        <?= csrf_field() ?>
                        <div>
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= e($username) ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>

                    <div class="small text-body-secondary mt-3">
                        Default: `admin / admin123` and `attendant / attendant123`
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

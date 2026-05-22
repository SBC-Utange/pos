<?php
/** @var array $config */
/** @var array $settings */
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
/** @var array $currentUser */
$userRole = $currentUser['role'] ?? 'attendant';
$salesOpen = in_array($currentPage, ['sales.php', 'sale_create.php'], true);
$managementOpen = in_array($currentPage, ['services.php', 'reports.php', 'profile.php'], true);
$isAdmin = $userRole === 'admin';
$dashboardPage = $isAdmin ? 'index.php' : 'attendant_dashboard.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($settings['business_name']) ?> - Sales Manager</title>
    <script>
        (function () {
            var savedTheme = localStorage.getItem('srm-theme');
            var theme = savedTheme === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-body-tertiary">
<div class="d-flex min-vh-100">
    <aside class="offcanvas-lg offcanvas-start text-bg-dark border-end" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel" style="width:280px;">
        <div class="offcanvas-header border-bottom border-secondary-subtle">
            <div>
                <h5 class="offcanvas-title mb-0" id="appSidebarLabel"><?= e($settings['business_name']) ?></h5>
                <small class="text-secondary">Sales Record Manager</small>
            </div>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-2">
            <nav class="nav nav-pills flex-column gap-1">
                <a class="nav-link text-start <?= in_array($currentPage, ['index.php', 'attendant_dashboard.php'], true) ? 'active' : 'text-light' ?>" href="<?= e($dashboardPage) ?>">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>

                <button class="btn btn-dark border-0 text-start d-flex justify-content-between align-items-center px-3 py-2 rounded <?= $salesOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#salesMenu" aria-expanded="<?= $salesOpen ? 'true' : 'false' ?>" aria-controls="salesMenu">
                    <span><i class="bi bi-cart3 me-2"></i>Sales</span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="collapse <?= $salesOpen ? 'show' : '' ?>" id="salesMenu">
                    <div class="nav nav-pills flex-column ms-2 gap-1">
                        <a class="nav-link text-start <?= $currentPage === 'sales.php' ? 'active' : 'text-light' ?>" href="sales.php">
                            <i class="bi bi-receipt me-2"></i>Sales List
                        </a>
                        <a class="nav-link text-start <?= $currentPage === 'sale_create.php' ? 'active' : 'text-light' ?>" href="sale_create.php">
                            <i class="bi bi-plus-circle me-2"></i>New Sale
                        </a>
                    </div>
                </div>

                <button class="btn btn-dark border-0 text-start d-flex justify-content-between align-items-center px-3 py-2 rounded <?= $managementOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#managementMenu" aria-expanded="<?= $managementOpen ? 'true' : 'false' ?>" aria-controls="managementMenu">
                    <span><i class="bi bi-sliders me-2"></i>Management</span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <div class="collapse <?= $managementOpen ? 'show' : '' ?>" id="managementMenu">
                    <div class="nav nav-pills flex-column ms-2 gap-1">
                        <a class="nav-link text-start <?= $currentPage === 'services.php' ? 'active' : 'text-light' ?>" href="services.php">
                            <i class="bi bi-grid me-2"></i>Services
                        </a>
                        <?php if ($isAdmin): ?>
                            <a class="nav-link text-start <?= $currentPage === 'reports.php' ? 'active' : 'text-light' ?>" href="reports.php">
                                <i class="bi bi-bar-chart me-2"></i>Reports
                            </a>
                        <?php endif; ?>
                        <a class="nav-link text-start <?= $currentPage === 'profile.php' ? 'active' : 'text-light' ?>" href="profile.php">
                            <i class="bi bi-person-badge me-2"></i>Profile
                        </a>
                    </div>
                </div>
            </nav>
        </div>
    </aside>

    <div class="flex-grow-1 d-flex flex-column min-vh-100">
        <header class="navbar bg-body border-bottom px-3">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <div class="fw-semibold text-body-secondary small">Operations Panel</div>
                    <div class="text-body-tertiary small"><?= e(date('D, d M Y')) ?></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-secondary"><?= e($currentUser['full_name'] !== '' ? $currentUser['full_name'] : $currentUser['username']) ?> (<?= e($userRole) ?>)</span>
                <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" id="themeToggle">
                    <i class="bi bi-moon-stars-fill"></i>
                    <span id="themeToggleLabel">Dark</span>
                </button>
                <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
            </div>
        </header>
        <main class="container-fluid py-4 px-3 px-lg-4">

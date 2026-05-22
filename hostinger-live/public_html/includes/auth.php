<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function current_user(): ?array
{
    $user = $_SESSION['auth_user'] ?? null;
    return is_array($user) ? $user : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(mysqli $mysqli, string $username, string $password): bool
{
    $stmt = $mysqli->prepare('SELECT u.id, u.username, u.password_hash, u.role, p.full_name
                              FROM users u
                              LEFT JOIN user_profiles p ON p.user_id = u.id
                              WHERE u.username = ? AND u.is_active = 1
                              LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
        'full_name' => (string) ($user['full_name'] ?? ''),
    ];

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function user_has_role(string $role): bool
{
    $user = current_user();
    return $user !== null && ($user['role'] ?? '') === $role;
}

function require_role(string $role): void
{
    require_login();
    if (!user_has_role($role)) {
        $user = current_user();
        if (($user['role'] ?? '') === 'attendant') {
            redirect('attendant_dashboard.php');
        }
        redirect('index.php');
    }
}

function enforce_attendant_page_access(string $page): void
{
    if (!user_has_role('attendant')) {
        return;
    }

    $allowed = ['attendant_dashboard.php', 'sales.php', 'sale_create.php', 'services.php', 'profile.php', 'logout.php'];
    if (!in_array($page, $allowed, true)) {
        redirect('attendant_dashboard.php');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_die(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(419);
        die('Invalid form token. Please refresh and try again.');
    }
}

<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_money(float $amount, string $currency): string
{
    return $currency . ' ' . number_format($amount, 2);
}

function fetch_services(mysqli $mysqli): array
{
    $sql = "SELECT s.id,
                   s.name,
                   s.parent_id,
                   p.name AS parent_name,
                   s.default_price,
                   CONCAT(COALESCE(CONCAT(p.name, ' > '), ''), s.name) AS display_name
            FROM services s
            LEFT JOIN services p ON p.id = s.parent_id
            WHERE s.is_active = 1
            ORDER BY COALESCE(p.name, s.name), s.parent_id IS NOT NULL, s.name";
    $result = $mysqli->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_service_by_id(mysqli $mysqli, int $id): ?array
{
    $stmt = $mysqli->prepare('SELECT id, name, parent_id, default_price FROM services WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function get_settings(mysqli $mysqli): array
{
    $result = $mysqli->query('SELECT business_name, currency_symbol FROM settings ORDER BY id ASC LIMIT 1');
    $row = $result ? $result->fetch_assoc() : null;

    return $row ?: ['business_name' => 'Shakesbeard CYBER', 'currency_symbol' => 'KES'];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function fetch_service_parents(mysqli $mysqli, ?int $excludeId = null): array
{
    $sql = 'SELECT id, name FROM services WHERE parent_id IS NULL';
    $params = [];
    $types = '';

    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
        $types .= 'i';
    }

    $sql .= ' ORDER BY name ASC';
    $stmt = $mysqli->prepare($sql);
    if ($params !== []) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

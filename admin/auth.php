<?php

declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';

const ADMIN_SESSION_KEY = 'ekinveselim_admin';

function admin_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('ekinveselim_admin');
        session_start();
    }
}

function admin_is_logged_in(): bool
{
    admin_start_session();
    return !empty($_SESSION[ADMIN_SESSION_KEY]);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function admin_get_settings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, username, password_hash, updated_at FROM admin_settings ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->exec("INSERT INTO admin_settings (username, password_hash) VALUES ('admin', NULL)");
        $stmt = $pdo->query('SELECT id, username, password_hash, updated_at FROM admin_settings ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
    }

    return $row ?: ['id' => 1, 'username' => 'admin', 'password_hash' => null, 'updated_at' => null];
}

function admin_password_is_set(array $settings): bool
{
    return !empty($settings['password_hash']);
}

function admin_require_password_if_set(PDO $pdo): void
{
    admin_require_login();

    $settings = admin_get_settings($pdo);
    if (!admin_password_is_set($settings)) {
        $current = basename($_SERVER['PHP_SELF'] ?? '');
        if ($current !== 'set-password.php') {
            header('Location: set-password.php');
            exit;
        }
    }
}

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y H:i', $timestamp);
}

function admin_format_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function admin_db_or_error(): PDO
{
    try {
        return app_db();
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><title>Yapılandırma</title></head><body>';
        echo '<p>Veritabanı bağlantısı kurulamadı. api/config.php dosyasını ve install.sql kurulumunu kontrol edin.</p>';
        echo '</body></html>';
        exit;
    }
}

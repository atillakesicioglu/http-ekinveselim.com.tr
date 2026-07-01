<?php

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $path = __DIR__ . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('api/config.php bulunamadı.');
        }

        $config = require $path;
    }

    return $config;
}

function app_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config()['db'] ?? [];

    if (empty($db['host']) || empty($db['name']) || empty($db['user'])) {
        throw new RuntimeException('Veritabanı ayarları eksik. api/config.php dosyasını doldurun.');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function sanitize_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function allowed_upload_mimes(): array
{
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/heic' => ['heic'],
        'image/heif' => ['heif'],
        'video/mp4' => ['mp4'],
        'video/quicktime' => ['mov'],
        'video/webm' => ['webm'],
        'video/3gpp' => ['3gp'],
        'video/x-msvideo' => ['avi'],
    ];
}

function detect_mime(string $path): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return '';
    }

    $mime = finfo_file($finfo, $path) ?: '';
    finfo_close($finfo);

    return $mime;
}

function is_allowed_upload(string $tmpPath, string $originalName): ?string
{
    $mime = detect_mime($tmpPath);
    $allowed = allowed_upload_mimes();

    if ($mime !== '' && isset($allowed[$mime])) {
        return $mime;
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    foreach ($allowed as $allowedMime => $extensions) {
        if (in_array($extension, $extensions, true)) {
            return $allowedMime;
        }
    }

    return null;
}

function ensure_upload_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }
}

function rate_limit_key(string $scope): string
{
    return hash('sha256', $scope . '|' . client_ip());
}

function is_rate_limited(string $scope, int $maxAttempts, int $windowSeconds): bool
{
    $file = sys_get_temp_dir() . '/ekinveselim_' . rate_limit_key($scope) . '.json';
    $now = time();
    $data = ['count' => 0, 'start' => $now];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && isset($decoded['count'], $decoded['start'])) {
            $data = $decoded;
        }
    }

    if (($now - (int) $data['start']) > $windowSeconds) {
        $data = ['count' => 0, 'start' => $now];
    }

    $data['count'] = (int) $data['count'] + 1;
    file_put_contents($file, json_encode($data));

    return $data['count'] > $maxAttempts;
}

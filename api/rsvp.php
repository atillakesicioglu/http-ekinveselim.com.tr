<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Geçersiz istek.'], 405);
}

if (is_rate_limited('rsvp', 10, 3600)) {
    json_response(['ok' => false, 'message' => 'Çok fazla deneme yaptınız. Lütfen biraz sonra tekrar deneyin.'], 429);
}

try {
    $pdo = app_db();
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Sunucu yapılandırması eksik.'], 500);
}

$data = read_json_body();

$name = sanitize_text((string) ($data['name'] ?? ''), 120);
$attendance = (string) ($data['attendance'] ?? '');
$guests = (int) ($data['guests'] ?? 1);
$message = sanitize_text((string) ($data['message'] ?? ''), 2000);

if ($name === '') {
    json_response(['ok' => false, 'message' => 'Ad soyad gerekli.'], 422);
}

if (!in_array($attendance, ['yes', 'no'], true)) {
    json_response(['ok' => false, 'message' => 'Katılım seçimi gerekli.'], 422);
}

if ($guests < 1) {
    $guests = 1;
}

if ($guests > 20) {
    $guests = 20;
}

if ($attendance === 'no') {
    $guests = 0;
}

$stmt = $pdo->prepare(
    'INSERT INTO rsvps (name, attendance, guests, message, ip_address) VALUES (:name, :attendance, :guests, :message, :ip_address)'
);

$stmt->execute([
    ':name' => $name,
    ':attendance' => $attendance,
    ':guests' => $guests,
    ':message' => $message !== '' ? $message : null,
    ':ip_address' => client_ip(),
]);

json_response([
    'ok' => true,
    'message' => 'Yanıtınız için teşekkür ederiz.',
]);

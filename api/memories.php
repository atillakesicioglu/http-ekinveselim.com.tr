<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Geçersiz istek.'], 405);
}

if (is_rate_limited('memories', 20, 3600)) {
    json_response(['ok' => false, 'message' => 'Çok fazla yükleme denemesi yaptınız. Lütfen biraz sonra tekrar deneyin.'], 429);
}

try {
    $pdo = app_db();
    $config = app_config();
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Sunucu yapılandırması eksik.'], 500);
}

$name = sanitize_text((string) ($_POST['name'] ?? ''), 120);
$note = sanitize_text((string) ($_POST['note'] ?? ''), 2000);

if ($name === '') {
    json_response(['ok' => false, 'message' => 'Ad soyad gerekli.'], 422);
}

if (empty($_FILES['files'])) {
    json_response(['ok' => false, 'message' => 'En az bir fotoğraf veya video seçin.'], 422);
}

$files = $_FILES['files'];
$fileCount = is_array($files['name']) ? count($files['name']) : 0;
$maxFiles = (int) ($config['upload']['max_files_per_request'] ?? 50);
$maxFileSize = (int) ($config['upload']['max_file_size'] ?? 104857600);
$baseDirectory = (string) ($config['upload']['directory'] ?? (__DIR__ . '/../uploads/memories'));

if ($fileCount < 1) {
    json_response(['ok' => false, 'message' => 'En az bir fotoğraf veya video seçin.'], 422);
}

if ($fileCount > $maxFiles) {
    json_response(['ok' => false, 'message' => 'Tek seferde en fazla ' . $maxFiles . ' dosya yükleyebilirsiniz.'], 422);
}

$year = date('Y');
$month = date('m');
$targetDirectory = rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

try {
    ensure_upload_directory($targetDirectory);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Yükleme klasörü hazırlanamadı.'], 500);
}

$uploadStmt = $pdo->prepare(
    'INSERT INTO memory_uploads (uploader_name, note, ip_address) VALUES (:uploader_name, :note, :ip_address)'
);
$uploadStmt->execute([
    ':uploader_name' => $name,
    ':note' => $note !== '' ? $note : null,
    ':ip_address' => client_ip(),
]);
$uploadId = (int) $pdo->lastInsertId();

$fileStmt = $pdo->prepare(
    'INSERT INTO memory_files (upload_id, original_name, stored_name, mime_type, file_size, relative_path)
     VALUES (:upload_id, :original_name, :stored_name, :mime_type, :file_size, :relative_path)'
);

$savedCount = 0;
$errors = [];

for ($i = 0; $i < $fileCount; $i++) {
    $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    $originalName = (string) ($files['name'][$i] ?? '');
    $tmpName = (string) ($files['tmp_name'][$i] ?? '');
    $size = (int) ($files['size'][$i] ?? 0);

    if ($error === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = $originalName !== '' ? $originalName : 'Dosya ' . ($i + 1);
        continue;
    }

    if ($size <= 0 || $size > $maxFileSize) {
        $errors[] = ($originalName !== '' ? $originalName : 'Dosya ' . ($i + 1)) . ' boyut sınırını aşıyor.';
        continue;
    }

    if (!is_uploaded_file($tmpName)) {
        $errors[] = ($originalName !== '' ? $originalName : 'Dosya ' . ($i + 1)) . ' geçersiz.';
        continue;
    }

    $mime = is_allowed_upload($tmpName, $originalName);
    if ($mime === null) {
        $errors[] = ($originalName !== '' ? $originalName : 'Dosya ' . ($i + 1)) . ' desteklenmiyor.';
        continue;
    }

    $extension = allowed_upload_mimes()[$mime][0] ?? 'bin';
    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $relativePath = $year . '/' . $month . '/' . $storedName;
    $destination = $targetDirectory . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpName, $destination)) {
        $errors[] = ($originalName !== '' ? $originalName : 'Dosya ' . ($i + 1)) . ' kaydedilemedi.';
        continue;
    }

    $fileStmt->execute([
        ':upload_id' => $uploadId,
        ':original_name' => sanitize_text($originalName, 255),
        ':stored_name' => $storedName,
        ':mime_type' => $mime,
        ':file_size' => $size,
        ':relative_path' => $relativePath,
    ]);

    $savedCount++;
}

if ($savedCount === 0) {
    $pdo->prepare('DELETE FROM memory_uploads WHERE id = :id')->execute([':id' => $uploadId]);
    $message = 'Hiçbir dosya yüklenemedi.';
    if ($errors !== []) {
        $message .= ' ' . implode(' ', array_slice($errors, 0, 3));
    }
    json_response(['ok' => false, 'message' => $message], 422);
}

$message = $savedCount . ' dosya başarıyla yüklendi. Teşekkür ederiz.';
if ($errors !== []) {
    $message .= ' Bazı dosyalar yüklenemedi.';
}

json_response([
    'ok' => true,
    'message' => $message,
    'uploaded' => $savedCount,
    'failed' => count($errors),
]);

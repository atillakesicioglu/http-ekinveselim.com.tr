<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

admin_start_session();
$pdo = admin_db_or_error();
admin_require_login();

$settings = admin_get_settings($pdo);
if (admin_password_is_set($settings)) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalı.';
    } elseif ($password !== $confirm) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE admin_settings SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':password_hash' => $hash,
            ':id' => (int) $settings['id'],
        ]);

        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Şifre Belirle</title>
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body class="admin-auth-page">
    <main class="admin-card">
      <h1>Şifre Belirle</h1>
      <p class="admin-muted">İlk giriş tamam. Sonraki girişlerde bu şifreyi kullanacaksınız.</p>

      <?php if ($error !== ''): ?>
        <div class="admin-alert admin-alert--error"><?= admin_h($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form">
        <label>
          Yeni şifre
          <input type="password" name="password" autocomplete="new-password" minlength="6" required />
        </label>
        <label>
          Şifre tekrar
          <input type="password" name="password_confirm" autocomplete="new-password" minlength="6" required />
        </label>
        <button type="submit">Şifreyi kaydet</button>
      </form>
    </main>
  </body>
</html>

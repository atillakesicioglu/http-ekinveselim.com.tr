<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

admin_start_session();
$pdo = admin_db_or_error();
$settings = admin_get_settings($pdo);

if (admin_is_logged_in() && admin_password_is_set($settings)) {
    header('Location: dashboard.php');
    exit;
}

if (admin_is_logged_in() && !admin_password_is_set($settings)) {
    header('Location: set-password.php');
    exit;
}

$error = '';
$passwordRequired = admin_password_is_set($settings);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username !== ($settings['username'] ?? 'admin')) {
        $error = 'Kullanıcı adı veya şifre hatalı.';
    } elseif (!$passwordRequired) {
        $_SESSION[ADMIN_SESSION_KEY] = true;
        header('Location: set-password.php');
        exit;
    } elseif ($password === '' || !password_verify($password, (string) $settings['password_hash'])) {
        $error = 'Kullanıcı adı veya şifre hatalı.';
    } else {
        $_SESSION[ADMIN_SESSION_KEY] = true;
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
    <title>Admin Girişi</title>
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body class="admin-auth-page">
    <main class="admin-card">
      <h1>Admin Girişi</h1>
      <p class="admin-muted">
        <?php if ($passwordRequired): ?>
          Kullanıcı adı ve şifrenizle giriş yapın.
        <?php else: ?>
          İlk giriş için kullanıcı adı <strong>admin</strong> yeterli. Girişten sonra şifre belirleyeceksiniz.
        <?php endif; ?>
      </p>

      <?php if ($error !== ''): ?>
        <div class="admin-alert admin-alert--error"><?= admin_h($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form">
        <label>
          Kullanıcı adı
          <input type="text" name="username" value="admin" autocomplete="username" required />
        </label>

        <?php if ($passwordRequired): ?>
          <label>
            Şifre
            <input type="password" name="password" autocomplete="current-password" required />
          </label>
        <?php else: ?>
          <input type="hidden" name="password" value="" />
        <?php endif; ?>

        <button type="submit">Giriş yap</button>
      </form>
    </main>
  </body>
</html>

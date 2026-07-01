<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

admin_start_session();
$pdo = admin_db_or_error();
admin_require_password_if_set($pdo);

$tab = (string) ($_GET['tab'] ?? 'rsvp');
if (!in_array($tab, ['rsvp', 'memories'], true)) {
    $tab = 'rsvp';
}

$attendanceFilter = (string) ($_GET['attendance'] ?? 'all');
if (!in_array($attendanceFilter, ['all', 'yes', 'no'], true)) {
    $attendanceFilter = 'all';
}

$rsvps = [];
$memoryUploads = [];

try {
    if ($tab === 'rsvp') {
        $sql = 'SELECT id, name, attendance, guests, message, created_at FROM rsvps';
        $params = [];

        if ($attendanceFilter !== 'all') {
            $sql .= ' WHERE attendance = :attendance';
            $params[':attendance'] = $attendanceFilter;
        }

        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rsvps = $stmt->fetchAll();
    } else {
        $uploadStmt = $pdo->query(
            'SELECT mu.id, mu.uploader_name, mu.note, mu.created_at,
                    COUNT(mf.id) AS file_count,
                    COALESCE(SUM(mf.file_size), 0) AS total_size
             FROM memory_uploads mu
             LEFT JOIN memory_files mf ON mf.upload_id = mu.id
             GROUP BY mu.id
             ORDER BY mu.created_at DESC'
        );
        $memoryUploads = $uploadStmt->fetchAll();

        $filesStmt = $pdo->query(
            'SELECT id, upload_id, original_name, mime_type, file_size, relative_path, created_at
             FROM memory_files
             ORDER BY created_at DESC'
        );
        $allFiles = $filesStmt->fetchAll();
        $filesByUpload = [];

        foreach ($allFiles as $file) {
            $uploadId = (int) $file['upload_id'];
            $filesByUpload[$uploadId][] = $file;
        }
    }
} catch (Throwable $e) {
    $errorMessage = 'Veriler okunurken hata oluştu. install.sql kurulumunu kontrol edin.';
}
?>
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Panel</title>
    <link rel="stylesheet" href="admin.css" />
  </head>
  <body class="admin-page">
    <header class="admin-header">
      <div>
        <h1>Ekin &amp; Selim Admin</h1>
        <p class="admin-muted">Katılım yanıtları ve yüklenen anılar</p>
      </div>
      <a class="admin-logout" href="logout.php">Çıkış</a>
    </header>

    <nav class="admin-tabs">
      <a class="<?= $tab === 'rsvp' ? 'is-active' : '' ?>" href="dashboard.php?tab=rsvp">Katılımlar</a>
      <a class="<?= $tab === 'memories' ? 'is-active' : '' ?>" href="dashboard.php?tab=memories">Anılar</a>
    </nav>

    <main class="admin-content">
      <?php if (!empty($errorMessage)): ?>
        <div class="admin-alert admin-alert--error"><?= admin_h($errorMessage) ?></div>
      <?php endif; ?>

      <?php if ($tab === 'rsvp'): ?>
        <div class="admin-toolbar">
          <span><?= count($rsvps) ?> kayıt</span>
          <div class="admin-filters">
            <a class="<?= $attendanceFilter === 'all' ? 'is-active' : '' ?>" href="dashboard.php?tab=rsvp&amp;attendance=all">Tümü</a>
            <a class="<?= $attendanceFilter === 'yes' ? 'is-active' : '' ?>" href="dashboard.php?tab=rsvp&amp;attendance=yes">Katılacak</a>
            <a class="<?= $attendanceFilter === 'no' ? 'is-active' : '' ?>" href="dashboard.php?tab=rsvp&amp;attendance=no">Katılamayacak</a>
          </div>
        </div>

        <?php if ($rsvps === []): ?>
          <p class="admin-empty">Henüz katılım yanıtı yok.</p>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Ad Soyad</th>
                  <th>Katılım</th>
                  <th>Kişi</th>
                  <th>Mesaj</th>
                  <th>Tarih</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rsvps as $row): ?>
                  <tr>
                    <td><?= admin_h($row['name']) ?></td>
                    <td>
                      <span class="admin-badge <?= $row['attendance'] === 'yes' ? 'admin-badge--yes' : 'admin-badge--no' ?>">
                        <?= $row['attendance'] === 'yes' ? 'Katılacak' : 'Katılamayacak' ?>
                      </span>
                    </td>
                    <td><?= (int) $row['guests'] ?></td>
                    <td><?= admin_h($row['message'] ?? '-') ?></td>
                    <td><?= admin_h(admin_format_date($row['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="admin-toolbar">
          <span><?= count($memoryUploads) ?> yükleme</span>
        </div>

        <?php if ($memoryUploads === []): ?>
          <p class="admin-empty">Henüz anı yüklenmedi.</p>
        <?php else: ?>
          <div class="admin-memory-list">
            <?php foreach ($memoryUploads as $upload): ?>
              <?php $uploadId = (int) $upload['id']; ?>
              <article class="admin-memory-card">
                <header>
                  <div>
                    <h2><?= admin_h($upload['uploader_name']) ?></h2>
                    <p class="admin-muted">
                      <?= admin_h(admin_format_date($upload['created_at'])) ?>
                      · <?= (int) $upload['file_count'] ?> dosya
                      · <?= admin_h(admin_format_size((int) $upload['total_size'])) ?>
                    </p>
                  </div>
                </header>

                <?php if (!empty($upload['note'])): ?>
                  <p class="admin-memory-note"><?= nl2br(admin_h($upload['note'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($filesByUpload[$uploadId])): ?>
                  <div class="admin-memory-files">
                    <?php foreach ($filesByUpload[$uploadId] as $file): ?>
                      <?php
                        $isImage = str_starts_with((string) $file['mime_type'], 'image/');
                        $fileUrl = '../uploads/memories/' . $file['relative_path'];
                      ?>
                      <a class="admin-memory-file" href="<?= admin_h($fileUrl) ?>" target="_blank" rel="noopener">
                        <?php if ($isImage): ?>
                          <img src="<?= admin_h($fileUrl) ?>" alt="<?= admin_h($file['original_name']) ?>" loading="lazy" />
                        <?php else: ?>
                          <div class="admin-memory-video">
                            <span>Video</span>
                            <small><?= admin_h($file['original_name']) ?></small>
                          </div>
                        <?php endif; ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </body>
</html>

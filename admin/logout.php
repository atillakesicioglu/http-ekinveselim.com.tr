<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

admin_start_session();
$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;

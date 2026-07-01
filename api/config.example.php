<?php

return [
    'db' => [
        'host' => '',
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'max_file_size' => 104857600,
        'max_files_per_request' => 50,
        'directory' => __DIR__ . '/../uploads/memories',
    ],
];

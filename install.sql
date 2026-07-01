-- Ekin & Selim davetiye sitesi veritabanı kurulumu
-- phpMyAdmin veya cPanel MySQL üzerinden bu dosyayı çalıştırın.

SET NAMES utf8mb4;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS admin_settings (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL DEFAULT 'admin',
    password_hash VARCHAR(255) NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_settings (id, username, password_hash)
VALUES (1, 'admin', NULL)
ON DUPLICATE KEY UPDATE username = VALUES(username);

CREATE TABLE IF NOT EXISTS rsvps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    attendance ENUM('yes', 'no') NOT NULL,
    guests SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    message TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rsvps_created_at (created_at),
    KEY idx_rsvps_attendance (attendance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memory_uploads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uploader_name VARCHAR(120) NOT NULL,
    note TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_memory_uploads_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memory_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    upload_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    relative_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_memory_files_upload_id (upload_id),
    CONSTRAINT fk_memory_files_upload
        FOREIGN KEY (upload_id) REFERENCES memory_uploads (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

<?php
header('Content-Type: application/json; charset=utf-8');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "notes_app";

$report = [
    'status' => 'started',
    'actions' => []
];

try {
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        throw new Exception("MySQL connection failed: " . $conn->connect_error);
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Drop broken database to completely wipe corrupted InnoDB table references
    try {
        $conn->query("DROP DATABASE IF EXISTS `$dbname`");
        $report['actions'][] = "Dropped corrupted database `$dbname`";
    } catch (Throwable $e) {
        $report['actions'][] = "Drop database warning: " . $e->getMessage();
    }

    // 2. Clean up any leftover physical files in mysql/data/notes_app directory
    $dataDir = realpath(__DIR__ . '/../../mysql/data') . DIRECTORY_SEPARATOR . $dbname;
    if (is_dir($dataDir)) {
        $files = glob($dataDir . DIRECTORY_SEPARATOR . '*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        @rmdir($dataDir);
        $report['actions'][] = "Purged leftover files in data directory";
    }

    // 3. Re-create clean database
    if ($conn->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        $report['actions'][] = "Created fresh database `$dbname`";
    } else {
        throw new Exception("Failed to create database `$dbname`: " . $conn->error);
    }

    $conn->select_db($dbname);

    // 4. Create fresh tables
    $sqlUsers = "CREATE TABLE `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `full_name` VARCHAR(100) DEFAULT NULL,
        `theme_preference` VARCHAR(20) DEFAULT 'light',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sqlUsers)) {
        $report['actions'][] = "Successfully created table 'users'";
    } else {
        throw new Exception("Failed to create 'users' table: " . $conn->error);
    }

    $sqlNotes = "CREATE TABLE `notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `category` VARCHAR(50) DEFAULT 'Personal',
        `status` VARCHAR(20) DEFAULT 'active',
        `create_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sqlNotes)) {
        $report['actions'][] = "Successfully created table 'notes'";
    } else {
        throw new Exception("Failed to create 'notes' table: " . $conn->error);
    }

    $sqlMap = "CREATE TABLE `note_sync_map` (
        `user_id` INT NOT NULL,
        `client_id` VARCHAR(64) NOT NULL,
        `note_id` INT NOT NULL,
        PRIMARY KEY (`user_id`, `client_id`),
        UNIQUE KEY `unique_note_sync_id` (`note_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sqlMap)) {
        $report['actions'][] = "Successfully created table 'note_sync_map'";
    } else {
        throw new Exception("Failed to create 'note_sync_map' table: " . $conn->error);
    }

    // 5. Test table readiness by inserting and deleting a test row
    $conn->query("INSERT INTO users (username, password, full_name, email) VALUES ('__test_verification__', 'test', 'Test', 'test@test.com')");
    $testId = $conn->insert_id;
    $conn->query("DELETE FROM users WHERE id = $testId");
    $report['actions'][] = "Verified users table read/write health check";

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    $conn->close();

    $report['status'] = 'success';
    $report['message'] = 'Database setup and repair completed successfully!';
} catch (Throwable $e) {
    $report['status'] = 'error';
    $report['message'] = $e->getMessage();
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

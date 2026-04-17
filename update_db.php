<?php
require_once 'config.php';

try {
    // Add columns to employees table if they don't exist
    $pdo->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL AFTER salary");
    $pdo->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER email");

    // Create Audit Logs Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Create Notifications Table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Update users role to include 'hr'
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'hr', 'employee') DEFAULT 'admin'");

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
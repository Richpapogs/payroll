<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS first_login BOOLEAN DEFAULT TRUE AFTER employee_id");
    echo "Column added successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
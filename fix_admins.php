<?php
require_once 'config.php';
try {
    $pdo->exec("UPDATE users SET first_login = FALSE WHERE role = 'admin'");
    echo "Existing admin accounts updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
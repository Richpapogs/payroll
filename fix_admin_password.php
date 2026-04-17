<?php
require_once 'config.php';

$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $result = $stmt->execute([$hashed_password, $username]);

    if ($result && $stmt->rowCount() > 0) {
        echo "Successfully updated admin password to 'admin123'!\n";
    } else {
        // If the admin user doesn't exist, create it
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$username, $hashed_password]);
            echo "Admin user did not exist. Successfully created 'admin' with password 'admin123'.\n";
        } else {
            echo "Admin password was already up-to-date or no changes were made.\n";
        }
    }
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>

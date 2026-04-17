<?php
require_once 'config.php';
$stmt = $pdo->query("DESCRIBE employees");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($columns);
?>
<?php
require_once '../database/db.php';

$newPassword = password_hash('123456', PASSWORD_DEFAULT);

try {
    $pdo->exec("UPDATE users SET password = '$newPassword'");
    echo "All passwords have been reset to: 123456";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
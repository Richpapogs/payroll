<?php
$hash = '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFv3U8U/C1sU9E7z5L1h8l2oI7s5Y6U.';
$password = 'admin123';
if (password_verify($password, $hash)) {
    echo "Password matches!";
} else {
    echo "Password DOES NOT match!";
}
?>

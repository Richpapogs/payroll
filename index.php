<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if (isAdmin()) {
        redirect('dashboard.php');
    } else {
        redirect('profile.php');
    }
} else {
    redirect('login.php');
}
?>

<?php
require_once 'config.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// Handle Mark as Read
if (isset($_GET['read'])) {
    $notif_id = (int)$_GET['read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    redirect('notifications.php');
}

// Handle Mark All as Read
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    redirect('notifications.php');
}

// Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

include 'header.php';
include 'sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1 fw-bold text-dark">Notifications</h2>
        <p class="text-muted small mb-0">Stay updated with system alerts and account activity</p>
    </div>
    <?php if (count($notifications) > 0): ?>
    <a href="?read_all=1" class="btn btn-sm btn-outline-primary shadow-sm">
        <i class="fas fa-check-double me-1"></i> Mark all as read
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-body p-0">
        <?php if (count($notifications) > 0): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item p-4 <?php echo $n['is_read'] ? 'bg-white' : 'bg-light-subtle border-start border-4 border-primary'; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark"><?php echo h($n['title']); ?></h6>
                                <p class="text-muted small mb-1"><?php echo h($n['message']); ?></p>
                                <span class="text-muted smaller"><i class="far fa-clock me-1"></i> <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php if (!$n['is_read']): ?>
                        <a href="?read=<?php echo $n['id']; ?>" class="btn btn-sm btn-link text-primary p-0" title="Mark as read">
                            <i class="fas fa-circle text-primary smaller"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3"><i class="fas fa-bell-slash fa-3x opacity-25"></i></div>
                <h6 class="fw-bold text-muted">No notifications yet</h6>
                <p class="text-muted small mb-0">We'll let you know when something important happens.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
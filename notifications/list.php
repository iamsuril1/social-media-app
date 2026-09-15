<?php
$pageTitle = "Notifications";
$pageCss = "notifications.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();

$mark_stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($mark_stmt, "i", $user_id);
mysqli_stmt_execute($mark_stmt);
mysqli_stmt_close($mark_stmt);

$stmt = mysqli_prepare($conn, "SELECT notifications.id, notifications.type, notifications.reference_id, 
                                       notifications.is_read, notifications.created_at,
                                       users.id AS actor_id, users.name AS actor_name, users.profile_pic AS actor_pic
                                FROM notifications
                                JOIN users ON notifications.actor_id = users.id
                                WHERE notifications.user_id = ?
                                ORDER BY notifications.created_at DESC
                                LIMIT 50");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$notifications = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>

<div class="notif-card">
    <h2>Notifications</h2>
    <div class="accent-bar"></div>

    <?php if (empty($notifications)): ?>
        <div class="notif-empty-state">
            <p>No notifications yet</p>
            <span class="placeholder-subtext">Likes, comments, and friend activity will show up here.</span>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $notif): ?>
                <?php $info = formatNotification($notif); ?>
                <a href="<?php echo $info['link']; ?>" class="notif-row <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                    <div class="notif-avatar"><?php echo renderAvatar($notif['actor_name'], $notif['actor_pic'], 'notif-avatar-img'); ?></div>
                    <div class="notif-content">
                        <span class="notif-text"><?php echo $info['text']; ?></span>
                        <span class="notif-time"><?php echo timeAgo($notif['created_at']); ?></span>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                        <span class="notif-dot"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
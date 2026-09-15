<?php
$pageTitle = "Chat";
$pageCss = "chat.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();

$friends_stmt = mysqli_prepare($conn, "SELECT users.id, users.name, users.profile_pic
                                        FROM friends
                                        JOIN users ON users.id = IF(friends.user_id = ?, friends.friend_id, friends.user_id)
                                        WHERE (friends.user_id = ? OR friends.friend_id = ?) AND friends.status = 'accepted'");
mysqli_stmt_bind_param($friends_stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($friends_stmt);
$friends = mysqli_fetch_all(mysqli_stmt_get_result($friends_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($friends_stmt);

$conversations = [];
foreach ($friends as $friend) {
    $friend_id = $friend['id'];

    $last_msg_stmt = mysqli_prepare($conn, "SELECT message, sender_id, created_at
                                             FROM messages
                                             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                                             ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($last_msg_stmt, "iiii", $user_id, $friend_id, $friend_id, $user_id);
    mysqli_stmt_execute($last_msg_stmt);
    $last_msg = mysqli_fetch_assoc(mysqli_stmt_get_result($last_msg_stmt));
    mysqli_stmt_close($last_msg_stmt);

    $unread_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM messages
                                          WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($unread_stmt, "ii", $friend_id, $user_id);
    mysqli_stmt_execute($unread_stmt);
    $unread_count = mysqli_fetch_assoc(mysqli_stmt_get_result($unread_stmt))['total'];
    mysqli_stmt_close($unread_stmt);

    $conversations[] = [
        'friend_id' => $friend_id,
        'friend_name' => $friend['name'],
        'friend_pic' => $friend['profile_pic'],
        'last_message' => $last_msg['message'] ?? null,
        'last_sender_id' => $last_msg['sender_id'] ?? null,
        'last_time' => $last_msg['created_at'] ?? null,
        'unread_count' => (int) $unread_count
    ];
}

usort($conversations, function($a, $b) {
    if ($a['last_time'] === null && $b['last_time'] === null) return 0;
    if ($a['last_time'] === null) return 1;
    if ($b['last_time'] === null) return -1;
    return strtotime($b['last_time']) - strtotime($a['last_time']);
});
?>

<div class="chat-inbox-card">
    <h2>Messages</h2>
    <div class="accent-bar"></div>

    <?php if (empty($conversations)): ?>
        <div class="chat-empty-state">
            <p>No conversations yet</p>
            <span class="placeholder-subtext">Add some friends to start chatting!</span>
        </div>
    <?php else: ?>
        <div class="conversation-list">
            <?php foreach ($conversations as $convo): ?>
                <a href="/social-media-app/chat/conversation.php?id=<?php echo $convo['friend_id']; ?>"
                   class="conversation-row <?php echo $convo['unread_count'] > 0 ? 'has-unread' : ''; ?>">

                    <div class="conversation-avatar">
                        <?php echo renderAvatar($convo['friend_name'], $convo['friend_pic'], 'convo-avatar-img'); ?>
                    </div>

                    <div class="conversation-info">
                        <span class="conversation-name"><?php echo htmlspecialchars($convo['friend_name']); ?></span>
                        <?php if ($convo['last_message']): ?>
                            <span class="conversation-preview">
                                <?php echo $convo['last_sender_id'] == $user_id ? 'You: ' : ''; ?><?php echo htmlspecialchars(mb_strimwidth($convo['last_message'], 0, 45, '...')); ?>
                            </span>
                        <?php else: ?>
                            <span class="conversation-preview no-messages">Say hello 👋</span>
                        <?php endif; ?>
                    </div>

                    <div class="conversation-meta">
                        <?php if ($convo['last_time']): ?>
                            <span class="conversation-time"><?php echo timeAgo($convo['last_time']); ?></span>
                        <?php endif; ?>
                        <?php if ($convo['unread_count'] > 0): ?>
                            <span class="unread-badge"><?php echo $convo['unread_count']; ?></span>
                        <?php endif; ?>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
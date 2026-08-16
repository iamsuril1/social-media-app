<?php
$pageTitle = "Friends";
$pageCss = "friends.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$flash = getFlash();

// 1. Pending requests received by me (others who sent me a request)
$requests_stmt = mysqli_prepare($conn, "SELECT users.id, users.name
                                         FROM friends
                                         JOIN users ON friends.user_id = users.id
                                         WHERE friends.friend_id = ? AND friends.status = 'pending'
                                         ORDER BY friends.created_at DESC");
mysqli_stmt_bind_param($requests_stmt, "i", $user_id);
mysqli_stmt_execute($requests_stmt);
$pending_requests = mysqli_fetch_all(mysqli_stmt_get_result($requests_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($requests_stmt);

// 2. My accepted friends (relation in either direction)
$friends_stmt = mysqli_prepare($conn, "SELECT users.id, users.name
                                        FROM friends
                                        JOIN users ON users.id = IF(friends.user_id = ?, friends.friend_id, friends.user_id)
                                        WHERE (friends.user_id = ? OR friends.friend_id = ?) AND friends.status = 'accepted'
                                        ORDER BY users.name ASC");
mysqli_stmt_bind_param($friends_stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($friends_stmt);
$my_friends = mysqli_fetch_all(mysqli_stmt_get_result($friends_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($friends_stmt);

// 3. Users I've already sent a pending request to (to show "Request Sent" state)
$sent_stmt = mysqli_prepare($conn, "SELECT friend_id FROM friends WHERE user_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($sent_stmt, "i", $user_id);
mysqli_stmt_execute($sent_stmt);
$sent_result = mysqli_fetch_all(mysqli_stmt_get_result($sent_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($sent_stmt);
$sent_ids = array_column($sent_result, 'friend_id');

// Build a set of "already connected in some way" ids to exclude from Discover
$connected_ids = array_merge(
    array_column($pending_requests, 'id'),
    array_column($my_friends, 'id'),
    $sent_ids
);
$connected_ids[] = $user_id; // exclude myself

// 4. Discover people — everyone else
$placeholders = implode(',', array_fill(0, count($connected_ids), '?'));
$types = str_repeat('i', count($connected_ids));

$discover_query = "SELECT id, name FROM users WHERE id NOT IN ($placeholders) ORDER BY name ASC LIMIT 20";
$discover_stmt = mysqli_prepare($conn, $discover_query);
mysqli_stmt_bind_param($discover_stmt, $types, ...$connected_ids);
mysqli_stmt_execute($discover_stmt);
$discover_users = mysqli_fetch_all(mysqli_stmt_get_result($discover_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($discover_stmt);

// 5. Who am I currently following? (for showing Follow/Following state in Discover)
$my_follows_stmt = mysqli_prepare($conn, "SELECT following_id FROM follows WHERE follower_id = ?");
mysqli_stmt_bind_param($my_follows_stmt, "i", $user_id);
mysqli_stmt_execute($my_follows_stmt);
$my_follows_result = mysqli_fetch_all(mysqli_stmt_get_result($my_follows_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($my_follows_stmt);
$my_following_ids = array_column($my_follows_result, 'following_id');
?>

<?php if ($flash): ?>
    <div class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<!-- Pending Requests -->
<?php if (!empty($pending_requests)): ?>
    <div class="friends-card">
        <h2>Friend Requests <span class="count-badge"><?php echo count($pending_requests); ?></span></h2>
        <div class="user-list">
            <?php foreach ($pending_requests as $req): ?>
                <div class="user-row">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo strtoupper(substr($req['name'], 0, 1)); ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($req['name']); ?></span>
                    </div>
                    <div class="user-actions">
                        <a href="/social-media-app/friends/respond.php?id=<?php echo $req['id']; ?>&action=accept" class="btn-accept">Accept</a>
                        <a href="/social-media-app/friends/respond.php?id=<?php echo $req['id']; ?>&action=reject" class="btn-reject">Decline</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- My Friends -->
<div class="friends-card">
    <h2>My Friends <span class="count-badge"><?php echo count($my_friends); ?></span></h2>

    <?php if (empty($my_friends)): ?>
        <p class="empty-text">You haven't added any friends yet. Check the suggestions below!</p>
    <?php else: ?>
        <div class="user-list">
            <?php foreach ($my_friends as $friend): ?>
                <div class="user-row">
                    <div class="user-info">
                        <div class="user-avatar friend-avatar"><?php echo strtoupper(substr($friend['name'], 0, 1)); ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($friend['name']); ?></span>
                    </div>
                    <div class="user-actions">
                        <a href="/social-media-app/friends/remove.php?id=<?php echo $friend['id']; ?>"
                           class="btn-remove"
                           onclick="return confirm('Remove this friend?');">Remove</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Discover People -->
<div class="friends-card">
    <h2>People You May Know</h2>

    <?php if (empty($discover_users)): ?>
        <p class="empty-text">No new people to show right now.</p>
    <?php else: ?>
        <div class="user-list">
            <?php foreach ($discover_users as $person): ?>
                <div class="user-row">
                    <div class="user-info">
                        <div class="user-avatar discover-avatar"><?php echo strtoupper(substr($person['name'], 0, 1)); ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($person['name']); ?></span>
                    </div>
                    <div class="user-actions">
                        <?php $is_following = in_array($person['id'], $my_following_ids); ?>
                        <button class="follow-toggle-btn <?php echo $is_following ? 'following' : ''; ?>"
                                onclick="toggleFollow(<?php echo $person['id']; ?>, this)">
                            <?php echo $is_following ? 'Following' : 'Follow'; ?>
                        </button>
                        <a href="/social-media-app/friends/add.php?id=<?php echo $person['id']; ?>" class="btn-add">Add Friend</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleFollow(targetId, btn) {
    btn.disabled = true;

    fetch('/social-media-app/follow/toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=' + encodeURIComponent(targetId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.following ? 'Following' : 'Follow';
            btn.classList.toggle('following', data.following);
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
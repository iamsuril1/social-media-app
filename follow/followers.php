<?php
$pageTitle = "Followers";
$pageCss = "friends.css";
require_once __DIR__ . '/../includes/header.php';

$current_user_id = currentUserId();
$profile_id = isset($_GET['id']) ? (int) $_GET['id'] : $current_user_id;

$name_stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($name_stmt, "i", $profile_id);
mysqli_stmt_execute($name_stmt);
$profile_name_row = mysqli_fetch_assoc(mysqli_stmt_get_result($name_stmt));
mysqli_stmt_close($name_stmt);

if (!$profile_name_row) {
    setFlash("User not found.");
    redirect('/social-media-app/index.php');
}
$profile_name = $profile_name_row['name'];

$stmt = mysqli_prepare($conn, "SELECT users.id, users.name
                                FROM follows
                                JOIN users ON follows.follower_id = users.id
                                WHERE follows.following_id = ?
                                ORDER BY follows.created_at DESC");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$followers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$my_follows_stmt = mysqli_prepare($conn, "SELECT following_id FROM follows WHERE follower_id = ?");
mysqli_stmt_bind_param($my_follows_stmt, "i", $current_user_id);
mysqli_stmt_execute($my_follows_stmt);
$my_follows_result = mysqli_fetch_all(mysqli_stmt_get_result($my_follows_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($my_follows_stmt);
$my_following_ids = array_column($my_follows_result, 'following_id');
?>

<div class="friends-card">
    <h2><?php echo htmlspecialchars($profile_name); ?>'s Followers <span class="count-badge"><?php echo count($followers); ?></span></h2>

    <?php if (empty($followers)): ?>
        <p class="empty-text">No followers yet.</p>
    <?php else: ?>
        <div class="user-list">
            <?php foreach ($followers as $person): ?>
                <div class="user-row">
                    <div class="user-info">
                        <div class="user-avatar discover-avatar"><?php echo strtoupper(substr($person['name'], 0, 1)); ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($person['name']); ?></span>
                    </div>
                    <?php if ($person['id'] != $current_user_id): ?>
                        <div class="user-actions">
                            <?php $is_following = in_array($person['id'], $my_following_ids); ?>
                            <button class="follow-toggle-btn <?php echo $is_following ? 'following' : ''; ?>"
                                    onclick="toggleFollow(<?php echo $person['id']; ?>, this)">
                                <?php echo $is_following ? 'Following' : 'Follow'; ?>
                            </button>
                        </div>
                    <?php endif; ?>
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
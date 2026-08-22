<?php
$pageTitle = "Profile";
$pageCss = "profile.css";
$extraCss = ["feed.css"];
require_once __DIR__ . '/../includes/header.php';

$current_user_id = currentUserId();
$profile_id = isset($_GET['id']) ? (int) $_GET['id'] : $current_user_id;
$is_own_profile = ($profile_id == $current_user_id);
$flash = getFlash();

$stmt = mysqli_prepare($conn, "SELECT id, name, email, bio, profile_pic, created_at FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $profile_id);
mysqli_stmt_execute($stmt);
$profile_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$profile_user) {
    setFlash("User not found.");
    redirect('/social-media-app/index.php');
}

$friend_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM friends 
                                             WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'");
mysqli_stmt_bind_param($friend_count_stmt, "ii", $profile_id, $profile_id);
mysqli_stmt_execute($friend_count_stmt);
$friend_count = mysqli_fetch_assoc(mysqli_stmt_get_result($friend_count_stmt))['total'];
mysqli_stmt_close($friend_count_stmt);

$followers_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM follows WHERE following_id = ?");
mysqli_stmt_bind_param($followers_stmt, "i", $profile_id);
mysqli_stmt_execute($followers_stmt);
$follower_count = mysqli_fetch_assoc(mysqli_stmt_get_result($followers_stmt))['total'];
mysqli_stmt_close($followers_stmt);

$following_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM follows WHERE follower_id = ?");
mysqli_stmt_bind_param($following_stmt, "i", $profile_id);
mysqli_stmt_execute($following_stmt);
$following_count = mysqli_fetch_assoc(mysqli_stmt_get_result($following_stmt))['total'];
mysqli_stmt_close($following_stmt);

$friend_status = null;
$is_following = false;

if (!$is_own_profile) {
    $rel_stmt = mysqli_prepare($conn, "SELECT user_id, status FROM friends 
                                        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    mysqli_stmt_bind_param($rel_stmt, "iiii", $current_user_id, $profile_id, $profile_id, $current_user_id);
    mysqli_stmt_execute($rel_stmt);
    $rel = mysqli_fetch_assoc(mysqli_stmt_get_result($rel_stmt));
    mysqli_stmt_close($rel_stmt);

    if ($rel) {
        if ($rel['status'] === 'accepted') {
            $friend_status = 'accepted';
        } elseif ($rel['user_id'] == $current_user_id) {
            $friend_status = 'pending_sent';
        } else {
            $friend_status = 'pending_received';
        }
    }

    $follow_stmt = mysqli_prepare($conn, "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    mysqli_stmt_bind_param($follow_stmt, "ii", $current_user_id, $profile_id);
    mysqli_stmt_execute($follow_stmt);
    mysqli_stmt_store_result($follow_stmt);
    $is_following = mysqli_stmt_num_rows($follow_stmt) > 0;
    mysqli_stmt_close($follow_stmt);
}

$posts_stmt = mysqli_prepare($conn, "SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
                                             users.id AS author_id, users.name AS author_name, users.profile_pic AS author_profile_pic,
                                             NULL AS sharer_id, NULL AS sharer_name
                                      FROM posts
                                      JOIN users ON posts.user_id = users.id
                                      WHERE posts.user_id = ?
                                      ORDER BY posts.created_at DESC");
mysqli_stmt_bind_param($posts_stmt, "i", $profile_id);
mysqli_stmt_execute($posts_stmt);
$profile_posts = mysqli_fetch_all(mysqli_stmt_get_result($posts_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($posts_stmt);
?>

<?php if ($flash): ?>
    <div class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="profile-header-card">
    <div class="profile-cover"></div>

    <div class="profile-info-row">
        <div class="profile-avatar-large">
            <?php echo renderAvatar($profile_user['name'], $profile_user['profile_pic'], 'avatar-preview-large'); ?>
        </div>

        <div class="profile-details">
            <h2 class="profile-name"><?php echo htmlspecialchars($profile_user['name']); ?></h2>
            <p class="profile-bio"><?php echo !empty($profile_user['bio']) ? nl2br(htmlspecialchars($profile_user['bio'])) : '<span class="no-bio">No bio yet.</span>'; ?></p>
            <p class="profile-joined">Joined <?php echo date("F Y", strtotime($profile_user['created_at'])); ?></p>
        </div>

        <div class="profile-actions">
            <?php if ($is_own_profile): ?>
                <a href="/social-media-app/profile/edit.php" class="btn-profile-action btn-edit-self">
                    <span class="btn-icon">✎</span> Edit Profile
                </a>
            <?php else: ?>
                <?php if ($friend_status === 'accepted'): ?>
                    <span class="btn-profile-action btn-friends-badge">
                        <span class="btn-icon">✓</span> Friends
                    </span>
                <?php elseif ($friend_status === 'pending_sent'): ?>
                    <span class="btn-profile-action btn-pending-badge">
                        <span class="btn-icon">⏳</span> Request Sent
                    </span>
                <?php elseif ($friend_status === 'pending_received'): ?>
                    <a href="/social-media-app/friends/respond.php?id=<?php echo $profile_id; ?>&action=accept" class="btn-profile-action btn-accept-request">
                        <span class="btn-icon">✓</span> Accept Request
                    </a>
                <?php else: ?>
                    <a href="/social-media-app/friends/add.php?id=<?php echo $profile_id; ?>" class="btn-profile-action btn-add-friend">
                        <span class="btn-icon">＋</span> Add Friend
                    </a>
                <?php endif; ?>

                <button class="btn-profile-action btn-follow-toggle <?php echo $is_following ? 'is-following' : ''; ?>"
                        id="profileFollowBtn"
                        onclick="toggleFollow(<?php echo $profile_id; ?>, this)">
                    <span class="btn-icon"><?php echo $is_following ? '✓' : '★'; ?></span>
                    <span class="btn-text"><?php echo $is_following ? 'Following' : 'Follow'; ?></span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-stats-row">
        <a href="/social-media-app/friends/list.php" class="profile-stat">
            <strong><?php echo $friend_count; ?></strong> Friends
        </a>
        <a href="/social-media-app/follow/followers.php?id=<?php echo $profile_id; ?>" class="profile-stat">
            <strong><?php echo $follower_count; ?></strong> Followers
        </a>
        <a href="/social-media-app/follow/following.php?id=<?php echo $profile_id; ?>" class="profile-stat">
            <strong><?php echo $following_count; ?></strong> Following
        </a>
        <span class="profile-stat">
            <strong><?php echo count($profile_posts); ?></strong> Posts
        </span>
    </div>
</div>

<div class="posts-feed">
    <?php if (empty($profile_posts)): ?>
        <div class="feed-placeholder">
            <p><?php echo $is_own_profile ? "You haven't" : htmlspecialchars($profile_user['name']) . " hasn't"; ?> posted anything yet</p>
        </div>
    <?php else: ?>
        <?php $delay = 0; ?>
        <?php foreach ($profile_posts as $row): ?>
            <?php include __DIR__ . '/../posts/post_card.php'; ?>
            <?php $delay += 0.06; ?>
        <?php endforeach; ?>
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
            const textEl = btn.querySelector('.btn-text');
            const iconEl = btn.querySelector('.btn-icon');
            if (textEl) textEl.textContent = data.following ? 'Following' : 'Follow';
            if (iconEl) iconEl.textContent = data.following ? '✓' : '★';
            btn.classList.toggle('is-following', data.following);
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}

function togglePostMenu(domId) {
    const menu = document.getElementById('menu-' + domId);
    const isOpen = menu.classList.contains('show');
    document.querySelectorAll('.post-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    if (!isOpen) menu.classList.add('show');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.post-menu')) {
        document.querySelectorAll('.post-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    }
});

function toggleLike(postId, domId) {
    const btn = document.getElementById('likeBtn-' + domId);
    btn.disabled = true;
    fetch('/social-media-app/posts/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + encodeURIComponent(postId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.liked ? '💙 Liked' : '👍 Like';
            btn.classList.toggle('liked', data.liked);
            document.getElementById('stats-' + domId).style.display = 'flex';
            const likeText = document.getElementById('likeCountText-' + domId);
            if (data.total_likes > 0) {
                likeText.style.display = 'inline';
                likeText.textContent = '👍 ' + data.total_likes + ' ' + (data.total_likes === 1 ? 'like' : 'likes');
            } else {
                likeText.style.display = 'none';
            }
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}

function toggleShare(postId, domId) {
    const btn = document.getElementById('shareBtn-' + domId);
    btn.disabled = true;
    fetch('/social-media-app/posts/share.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + encodeURIComponent(postId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.shared ? '✅ Shared' : '↗ Share';
            btn.classList.toggle('shared', data.shared);
            document.getElementById('stats-' + domId).style.display = 'flex';
            const shareText = document.getElementById('shareCountText-' + domId);
            if (data.total_shares > 0) {
                shareText.style.display = 'inline';
                shareText.textContent = '🔁 ' + data.total_shares + ' ' + (data.total_shares === 1 ? 'share' : 'shares');
            } else {
                shareText.style.display = 'none';
            }
            if (data.shared) setTimeout(() => location.reload(), 500);
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}

function toggleCommentBox(domId) {
    const section = document.getElementById('commentsSection-' + domId);
    section.style.display = (section.style.display === 'none') ? 'block' : 'none';
    if (section.style.display === 'block') {
        document.getElementById('commentInput-' + domId).focus();
    }
}

function submitComment(postId, domId) {
    const input = document.getElementById('commentInput-' + domId);
    const text = input.value.trim();
    if (text === '') return;

    fetch('/social-media-app/posts/comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + encodeURIComponent(postId) + '&comment=' + encodeURIComponent(text)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('commentsList-' + domId);
            const div = document.createElement('div');
            div.className = 'comment-item';
            div.id = 'comment-' + data.comment_id + '-' + domId;
            div.innerHTML = `
                <div class="comment-avatar">${data.user_name.charAt(0).toUpperCase()}</div>
                <div class="comment-bubble">
                    <span class="comment-author">${escapeHtml(data.user_name)}</span>
                    <span class="comment-text">${escapeHtml(data.comment)}</span>
                </div>
                <button class="comment-delete-btn" onclick="deleteComment(${data.comment_id}, '${domId}')" title="Delete comment">🗑</button>
            `;
            list.appendChild(div);
            document.getElementById('stats-' + domId).style.display = 'flex';
            const commentText = document.getElementById('commentCountText-' + domId);
            commentText.style.display = 'inline';
            commentText.textContent = data.total_comments + ' ' + (data.total_comments === 1 ? 'comment' : 'comments');
            input.value = '';
        } else {
            alert(data.message || 'Could not post comment.');
        }
    });
}

function deleteComment(commentId, domId) {
    if (!confirm('Delete this comment?')) return;
    fetch('/social-media-app/posts/comment_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'comment_id=' + encodeURIComponent(commentId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('comment-' + commentId + '-' + domId);
            if (el) el.remove();
            const commentText = document.getElementById('commentCountText-' + domId);
            if (data.total_comments > 0) {
                commentText.style.display = 'inline';
                commentText.textContent = data.total_comments + ' ' + (data.total_comments === 1 ? 'comment' : 'comments');
            } else {
                commentText.style.display = 'none';
            }
        } else {
            alert(data.message || 'Could not delete comment.');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
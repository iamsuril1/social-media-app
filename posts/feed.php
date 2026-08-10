<?php
// This file expects $conn to already be available (included after db.php)

$current_user_id = currentUserId();

$posts_query = "SELECT posts.id, posts.content, posts.image, posts.created_at, 
                        users.id AS user_id, users.name, users.profile_pic
                 FROM posts
                 JOIN users ON posts.user_id = users.id
                 ORDER BY posts.created_at DESC";

$posts_result = mysqli_query($conn, $posts_query);
?>

<div class="posts-feed">
    <?php if (mysqli_num_rows($posts_result) === 0): ?>

        <div class="feed-placeholder">
            <div class="placeholder-icon">
                <span class="dot dot-1"></span>
                <span class="dot dot-2"></span>
                <span class="dot dot-3"></span>
            </div>
            <p>Your feed is empty for now</p>
            <span class="placeholder-subtext">Be the first to share something!</span>
        </div>

    <?php else: ?>

        <?php $delay = 0; ?>
        <?php while ($post = mysqli_fetch_assoc($posts_result)): ?>

            <?php
            $post_id = $post['id'];

            // Total like count for this post
            $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
            mysqli_stmt_bind_param($count_stmt, "i", $post_id);
            mysqli_stmt_execute($count_stmt);
            $count_result = mysqli_stmt_get_result($count_stmt);
            $like_count = mysqli_fetch_assoc($count_result)['total'];
            mysqli_stmt_close($count_stmt);

            // Has the current user liked this post?
            $liked_stmt = mysqli_prepare($conn, "SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($liked_stmt, "ii", $post_id, $current_user_id);
            mysqli_stmt_execute($liked_stmt);
            mysqli_stmt_store_result($liked_stmt);
            $user_liked = mysqli_stmt_num_rows($liked_stmt) > 0;
            mysqli_stmt_close($liked_stmt);
            ?>

            <div class="post-card" style="animation-delay: <?php echo $delay; ?>s;">

                <div class="post-header">
                    <div class="post-avatar">
                        <?php echo strtoupper(substr($post['name'], 0, 1)); ?>
                    </div>
                    <div class="post-author-info">
                        <span class="post-author-name"><?php echo htmlspecialchars($post['name']); ?></span>
                        <span class="post-time"><?php echo timeAgo($post['created_at']); ?></span>
                    </div>

                    <?php if ($post['user_id'] == $current_user_id): ?>
                        <div class="post-menu">
                            <button class="post-menu-btn" onclick="togglePostMenu(<?php echo $post_id; ?>)">⋯</button>
                            <div class="post-menu-dropdown" id="menu-<?php echo $post_id; ?>">
                                <a href="/social-media-app/posts/edit.php?id=<?php echo $post_id; ?>" class="post-menu-item">
                                   ✏️ Edit
                                </a>
                                <a href="/social-media-app/posts/delete.php?id=<?php echo $post_id; ?>"
                                   class="post-menu-item delete"
                                   onclick="return confirm('Delete this post? This cannot be undone.');">
                                   🗑 Delete
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($post['content'])): ?>
                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($post['image'])): ?>
                    <div class="post-image-wrapper">
                        <img src="/social-media-app/assets/uploads/posts/<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="post-image">
                    </div>
                <?php endif; ?>

                <div class="post-stats" id="stats-<?php echo $post_id; ?>" style="<?php echo $like_count == 0 ? 'display:none;' : ''; ?>">
                    <span class="like-count-text" id="likeCountText-<?php echo $post_id; ?>">
                        👍 <?php echo $like_count; ?> <?php echo $like_count == 1 ? 'like' : 'likes'; ?>
                    </span>
                </div>

                <div class="post-actions">
                    <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>"
                            id="likeBtn-<?php echo $post_id; ?>"
                            onclick="toggleLike(<?php echo $post_id; ?>)">
                        <?php echo $user_liked ? '💙 Liked' : '👍 Like'; ?>
                    </button>
                    <button class="post-action-btn" disabled>💬 Comment</button>
                    <button class="post-action-btn" disabled>↗ Share</button>
                </div>

            </div>
            <?php $delay += 0.08; ?>
        <?php endwhile; ?>

    <?php endif; ?>
</div>

<script>
function togglePostMenu(postId) {
    const menu = document.getElementById('menu-' + postId);
    const isOpen = menu.classList.contains('show');
    document.querySelectorAll('.post-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    if (!isOpen) {
        menu.classList.add('show');
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.post-menu')) {
        document.querySelectorAll('.post-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    }
});

function toggleLike(postId) {
    const btn = document.getElementById('likeBtn-' + postId);
    const statsBox = document.getElementById('stats-' + postId);
    const countText = document.getElementById('likeCountText-' + postId);

    // Optimistic UI lock while request is in flight
    btn.disabled = true;

    fetch('/social-media-app/posts/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + encodeURIComponent(postId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.liked) {
                btn.textContent = '💙 Liked';
                btn.classList.add('liked');
            } else {
                btn.textContent = '👍 Like';
                btn.classList.remove('liked');
            }

            if (data.total_likes > 0) {
                statsBox.style.display = 'block';
                countText.textContent = '👍 ' + data.total_likes + ' ' + (data.total_likes === 1 ? 'like' : 'likes');
            } else {
                statsBox.style.display = 'none';
            }
        }
        btn.disabled = false;
    })
    .catch(() => {
        btn.disabled = false;
    });
}
</script>
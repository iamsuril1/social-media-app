<?php
$posts_query = "SELECT posts.id, posts.content, posts.image, posts.created_at, 
                        users.id AS user_id, users.name, users.profile_pic
                 FROM posts
                 JOIN users ON posts.user_id = users.id
                 ORDER BY posts.created_at DESC";

$posts_result = mysqli_query($conn, $posts_query);
$current_user_id = currentUserId();
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
                            <button class="post-menu-btn" onclick="togglePostMenu(<?php echo $post['id']; ?>)">⋯</button>
                            <div class="post-menu-dropdown" id="menu-<?php echo $post['id']; ?>">
                                <a href="/social-media-app/posts/edit.php?id=<?php echo $post['id']; ?>"
                                   class="post-menu-item">
                                   ✏️ Edit
                                </a>
                                <a href="/social-media-app/posts/delete.php?id=<?php echo $post['id']; ?>"
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

                <div class="post-actions">
                    <button class="post-action-btn" disabled>👍 Like</button>
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
</script>
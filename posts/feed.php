<?php

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

            // Like count + whether current user liked it
            $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
            mysqli_stmt_bind_param($count_stmt, "i", $post_id);
            mysqli_stmt_execute($count_stmt);
            $like_count = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
            mysqli_stmt_close($count_stmt);

            $liked_stmt = mysqli_prepare($conn, "SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($liked_stmt, "ii", $post_id, $current_user_id);
            mysqli_stmt_execute($liked_stmt);
            mysqli_stmt_store_result($liked_stmt);
            $user_liked = mysqli_stmt_num_rows($liked_stmt) > 0;
            mysqli_stmt_close($liked_stmt);

            // Comments for this post
            $comments_stmt = mysqli_prepare($conn, "SELECT comments.id, comments.comment, comments.created_at,
                                                             users.id AS commenter_id, users.name AS commenter_name
                                                      FROM comments
                                                      JOIN users ON comments.user_id = users.id
                                                      WHERE comments.post_id = ?
                                                      ORDER BY comments.created_at ASC");
            mysqli_stmt_bind_param($comments_stmt, "i", $post_id);
            mysqli_stmt_execute($comments_stmt);
            $comments_result = mysqli_stmt_get_result($comments_stmt);
            $comments = mysqli_fetch_all($comments_result, MYSQLI_ASSOC);
            mysqli_stmt_close($comments_stmt);
            $comment_count = count($comments);
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
                                <a href="/social-media-app/posts/edit.php?id=<?php echo $post_id; ?>" class="post-menu-item">✏️ Edit</a>
                                <a href="/social-media-app/posts/delete.php?id=<?php echo $post_id; ?>"
                                   class="post-menu-item delete"
                                   onclick="return confirm('Delete this post? This cannot be undone.');">🗑 Delete</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($post['content'])): ?>
                    <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                <?php endif; ?>

                <?php if (!empty($post['image'])): ?>
                    <div class="post-image-wrapper">
                        <img src="/social-media-app/assets/uploads/posts/<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="post-image">
                    </div>
                <?php endif; ?>

                <div class="post-stats" id="stats-<?php echo $post_id; ?>" style="<?php echo ($like_count == 0 && $comment_count == 0) ? 'display:none;' : ''; ?>">
                    <span class="like-count-text" id="likeCountText-<?php echo $post_id; ?>" style="<?php echo $like_count == 0 ? 'display:none;' : ''; ?>">
                        👍 <?php echo $like_count; ?> <?php echo $like_count == 1 ? 'like' : 'likes'; ?>
                    </span>
                    <span class="comment-count-text" id="commentCountText-<?php echo $post_id; ?>" style="<?php echo $comment_count == 0 ? 'display:none;' : ''; ?>">
                        <?php echo $comment_count; ?> <?php echo $comment_count == 1 ? 'comment' : 'comments'; ?>
                    </span>
                </div>

                <div class="post-actions">
                    <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>"
                            id="likeBtn-<?php echo $post_id; ?>"
                            onclick="toggleLike(<?php echo $post_id; ?>)">
                        <?php echo $user_liked ? '💙 Liked' : '👍 Like'; ?>
                    </button>
                    <button class="post-action-btn comment-toggle-btn" onclick="toggleCommentBox(<?php echo $post_id; ?>)">
                        💬 Comment
                    </button>
                    <button class="post-action-btn" disabled>↗ Share</button>
                </div>

                <div class="comments-section" id="commentsSection-<?php echo $post_id; ?>" style="display:none;">

                    <div class="comments-list" id="commentsList-<?php echo $post_id; ?>">
                        <?php foreach ($comments as $c): ?>
                            <div class="comment-item" id="comment-<?php echo $c['id']; ?>">
                                <div class="comment-avatar"><?php echo strtoupper(substr($c['commenter_name'], 0, 1)); ?></div>
                                <div class="comment-bubble">
                                    <span class="comment-author"><?php echo htmlspecialchars($c['commenter_name']); ?></span>
                                    <span class="comment-text"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></span>
                                </div>
                                <?php if ($c['commenter_id'] == $current_user_id): ?>
                                    <button class="comment-delete-btn" onclick="deleteComment(<?php echo $c['id']; ?>, <?php echo $post_id; ?>)" title="Delete comment">🗑</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="comment-form-row">
                        <div class="comment-avatar comment-avatar-self"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                        <input type="text" class="comment-input" id="commentInput-<?php echo $post_id; ?>"
                               placeholder="Write a comment..."
                               onkeypress="if(event.key==='Enter'){submitComment(<?php echo $post_id; ?>); event.preventDefault();}">
                        <button class="comment-send-btn" onclick="submitComment(<?php echo $post_id; ?>)">Send</button>
                    </div>

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
    if (!isOpen) menu.classList.add('show');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.post-menu')) {
        document.querySelectorAll('.post-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    }
});

function toggleLike(postId) {
    const btn = document.getElementById('likeBtn-' + postId);
    const statsBox = document.getElementById('stats-' + postId);
    const likeText = document.getElementById('likeCountText-' + postId);

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
                likeText.style.display = 'inline';
                likeText.textContent = '👍 ' + data.total_likes + ' ' + (data.total_likes === 1 ? 'like' : 'likes');
                statsBox.style.display = 'flex';
            } else {
                likeText.style.display = 'none';
            }
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}

function toggleCommentBox(postId) {
    const section = document.getElementById('commentsSection-' + postId);
    section.style.display = (section.style.display === 'none') ? 'block' : 'none';
    if (section.style.display === 'block') {
        document.getElementById('commentInput-' + postId).focus();
    }
}

function submitComment(postId) {
    const input = document.getElementById('commentInput-' + postId);
    const text = input.value.trim();
    if (text === '') return;

    fetch('/social-media-app/posts/comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + encodeURIComponent(postId) + '&comment=' + encodeURIComponent(text)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const list = document.getElementById('commentsList-' + postId);

            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment-item';
            commentDiv.id = 'comment-' + data.comment_id;
            commentDiv.innerHTML = `
                <div class="comment-avatar">${data.user_name.charAt(0).toUpperCase()}</div>
                <div class="comment-bubble">
                    <span class="comment-author">${escapeHtml(data.user_name)}</span>
                    <span class="comment-text">${escapeHtml(data.comment)}</span>
                </div>
                <button class="comment-delete-btn" onclick="deleteComment(${data.comment_id}, ${postId})" title="Delete comment">🗑</button>
            `;
            list.appendChild(commentDiv);

            const statsBox = document.getElementById('stats-' + postId);
            const commentText = document.getElementById('commentCountText-' + postId);
            statsBox.style.display = 'flex';
            commentText.style.display = 'inline';
            commentText.textContent = data.total_comments + ' ' + (data.total_comments === 1 ? 'comment' : 'comments');

            input.value = '';
        } else {
            alert(data.message || 'Could not post comment.');
        }
    });
}

function deleteComment(commentId, postId) {
    if (!confirm('Delete this comment?')) return;

    fetch('/social-media-app/posts/comment_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'comment_id=' + encodeURIComponent(commentId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('comment-' + commentId);
            if (el) el.remove();

            const statsBox = document.getElementById('stats-' + postId);
            const commentText = document.getElementById('commentCountText-' + postId);
            const likeText = document.getElementById('likeCountText-' + postId);

            if (data.total_comments > 0) {
                commentText.style.display = 'inline';
                commentText.textContent = data.total_comments + ' ' + (data.total_comments === 1 ? 'comment' : 'comments');
            } else {
                commentText.style.display = 'none';
            }

            if (likeText.style.display === 'none' && commentText.style.display === 'none') {
                statsBox.style.display = 'none';
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
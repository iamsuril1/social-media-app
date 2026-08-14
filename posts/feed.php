<?php
// This file expects $conn to already be available (included after db.php)

$current_user_id = currentUserId();

$feed_query = "
    (SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
            author.id AS author_id, author.name AS author_name,
            NULL AS sharer_id, NULL AS sharer_name, posts.created_at AS sort_time
     FROM posts
     JOIN users AS author ON posts.user_id = author.id)

    UNION ALL

    (SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
            author.id AS author_id, author.name AS author_name,
            sharer.id AS sharer_id, sharer.name AS sharer_name, shares.created_at AS sort_time
     FROM shares
     JOIN posts ON shares.post_id = posts.id
     JOIN users AS author ON posts.user_id = author.id
     JOIN users AS sharer ON shares.user_id = sharer.id)

    ORDER BY sort_time DESC
";

$feed_result = mysqli_query($conn, $feed_query);
?>

<div class="posts-feed">
    <?php if (mysqli_num_rows($feed_result) === 0): ?>

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
        <?php while ($row = mysqli_fetch_assoc($feed_result)): ?>

            <?php
            $post_id = $row['post_id'];
            $is_shared_entry = !empty($row['sharer_id']);

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

            // Share count + whether current user shared it
            $share_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM shares WHERE post_id = ?");
            mysqli_stmt_bind_param($share_count_stmt, "i", $post_id);
            mysqli_stmt_execute($share_count_stmt);
            $share_count = mysqli_fetch_assoc(mysqli_stmt_get_result($share_count_stmt))['total'];
            mysqli_stmt_close($share_count_stmt);

            $user_shared_stmt = mysqli_prepare($conn, "SELECT id FROM shares WHERE post_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($user_shared_stmt, "ii", $post_id, $current_user_id);
            mysqli_stmt_execute($user_shared_stmt);
            mysqli_stmt_store_result($user_shared_stmt);
            $user_shared = mysqli_stmt_num_rows($user_shared_stmt) > 0;
            mysqli_stmt_close($user_shared_stmt);

            // Comments for this post
            $comments_stmt = mysqli_prepare($conn, "SELECT comments.id, comments.comment, comments.created_at,
                                                             users.id AS commenter_id, users.name AS commenter_name
                                                      FROM comments
                                                      JOIN users ON comments.user_id = users.id
                                                      WHERE comments.post_id = ?
                                                      ORDER BY comments.created_at ASC");
            mysqli_stmt_bind_param($comments_stmt, "i", $post_id);
            mysqli_stmt_execute($comments_stmt);
            $comments = mysqli_fetch_all(mysqli_stmt_get_result($comments_stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($comments_stmt);
            $comment_count = count($comments);

            // Unique DOM id: original posts use just the post id,
            // shared entries get a suffix so the same post can appear twice safely
            $dom_id = $is_shared_entry ? $post_id . '_s' . $row['sharer_id'] : $post_id;
            ?>

            <div class="post-card" style="animation-delay: <?php echo $delay; ?>s;">

                <?php if ($is_shared_entry): ?>
                    <div class="shared-banner">
                        🔁 <strong><?php echo htmlspecialchars($row['sharer_name']); ?></strong> shared this post
                    </div>
                <?php endif; ?>

                <div class="post-header">
                    <div class="post-avatar">
                        <?php echo strtoupper(substr($row['author_name'], 0, 1)); ?>
                    </div>
                    <div class="post-author-info">
                        <span class="post-author-name"><?php echo htmlspecialchars($row['author_name']); ?></span>
                        <span class="post-time"><?php echo timeAgo($row['post_created_at']); ?></span>
                    </div>

                    <?php if (!$is_shared_entry && $row['author_id'] == $current_user_id): ?>
                        <div class="post-menu">
                            <button class="post-menu-btn" onclick="togglePostMenu('<?php echo $dom_id; ?>')">⋯</button>
                            <div class="post-menu-dropdown" id="menu-<?php echo $dom_id; ?>">
                                <a href="/social-media-app/posts/edit.php?id=<?php echo $post_id; ?>" class="post-menu-item">✏️ Edit</a>
                                <a href="/social-media-app/posts/delete.php?id=<?php echo $post_id; ?>"
                                   class="post-menu-item delete"
                                   onclick="return confirm('Delete this post? This cannot be undone.');">🗑 Delete</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($row['content'])): ?>
                    <div class="post-content"><?php echo nl2br(htmlspecialchars($row['content'])); ?></div>
                <?php endif; ?>

                <?php if (!empty($row['image'])): ?>
                    <div class="post-image-wrapper">
                        <img src="/social-media-app/assets/uploads/posts/<?php echo htmlspecialchars($row['image']); ?>" alt="Post image" class="post-image">
                    </div>
                <?php endif; ?>

                <div class="post-stats" id="stats-<?php echo $dom_id; ?>"
                     style="<?php echo ($like_count == 0 && $comment_count == 0 && $share_count == 0) ? 'display:none;' : ''; ?>">
                    <span class="like-count-text" id="likeCountText-<?php echo $dom_id; ?>" style="<?php echo $like_count == 0 ? 'display:none;' : ''; ?>">
                        👍 <?php echo $like_count; ?> <?php echo $like_count == 1 ? 'like' : 'likes'; ?>
                    </span>
                    <span class="comment-count-text" id="commentCountText-<?php echo $dom_id; ?>" style="<?php echo $comment_count == 0 ? 'display:none;' : ''; ?>">
                        <?php echo $comment_count; ?> <?php echo $comment_count == 1 ? 'comment' : 'comments'; ?>
                    </span>
                    <span class="share-count-text" id="shareCountText-<?php echo $dom_id; ?>" style="<?php echo $share_count == 0 ? 'display:none;' : ''; ?>">
                        🔁 <?php echo $share_count; ?> <?php echo $share_count == 1 ? 'share' : 'shares'; ?>
                    </span>
                </div>

                <div class="post-actions">
                    <button class="post-action-btn like-btn <?php echo $user_liked ? 'liked' : ''; ?>"
                            id="likeBtn-<?php echo $dom_id; ?>"
                            onclick="toggleLike(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>')">
                        <?php echo $user_liked ? '💙 Liked' : '👍 Like'; ?>
                    </button>
                    <button class="post-action-btn comment-toggle-btn" onclick="toggleCommentBox('<?php echo $dom_id; ?>')">
                        💬 Comment
                    </button>
                    <button class="post-action-btn share-btn <?php echo $user_shared ? 'shared' : ''; ?>"
                            id="shareBtn-<?php echo $dom_id; ?>"
                            onclick="toggleShare(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>')">
                        <?php echo $user_shared ? '✅ Shared' : '↗ Share'; ?>
                    </button>
                </div>

                <div class="comments-section" id="commentsSection-<?php echo $dom_id; ?>" style="display:none;">

                    <div class="comments-list" id="commentsList-<?php echo $dom_id; ?>">
                        <?php foreach ($comments as $c): ?>
                            <div class="comment-item" id="comment-<?php echo $c['id']; ?>-<?php echo $dom_id; ?>">
                                <div class="comment-avatar"><?php echo strtoupper(substr($c['commenter_name'], 0, 1)); ?></div>
                                <div class="comment-bubble">
                                    <span class="comment-author"><?php echo htmlspecialchars($c['commenter_name']); ?></span>
                                    <span class="comment-text"><?php echo nl2br(htmlspecialchars($c['comment'])); ?></span>
                                </div>
                                <?php if ($c['commenter_id'] == $current_user_id): ?>
                                    <button class="comment-delete-btn" onclick="deleteComment(<?php echo $c['id']; ?>, '<?php echo $dom_id; ?>')" title="Delete comment">🗑</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="comment-form-row">
                        <div class="comment-avatar comment-avatar-self"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
                        <input type="text" class="comment-input" id="commentInput-<?php echo $dom_id; ?>"
                               placeholder="Write a comment..."
                               onkeypress="if(event.key==='Enter'){submitComment(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>'); event.preventDefault();}">
                        <button class="comment-send-btn" onclick="submitComment(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>')">Send</button>
                    </div>

                </div>

            </div>
            <?php $delay += 0.08; ?>
        <?php endwhile; ?>

    <?php endif; ?>
</div>

<script>
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
            syncStatsRow(domId);
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
            syncStatsRow(domId);
            const shareText = document.getElementById('shareCountText-' + domId);
            if (data.total_shares > 0) {
                shareText.style.display = 'inline';
                shareText.textContent = '🔁 ' + data.total_shares + ' ' + (data.total_shares === 1 ? 'share' : 'shares');
            } else {
                shareText.style.display = 'none';
            }

            if (data.shared) {
                setTimeout(() => location.reload(), 500);
            }
        }
        btn.disabled = false;
    })
    .catch(() => { btn.disabled = false; });
}

function syncStatsRow(domId) {
    document.getElementById('stats-' + domId).style.display = 'flex';
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

            syncStatsRow(domId);
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
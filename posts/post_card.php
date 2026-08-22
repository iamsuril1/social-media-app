<?php
// Expects: $row (one feed row), $current_user_id, $conn, and optionally $delay
$delay = $delay ?? 0;

$post_id = $row['post_id'];
$is_shared_entry = !empty($row['sharer_id']);

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

$comments_stmt = mysqli_prepare($conn, "SELECT comments.id, comments.comment, comments.created_at,
                                                 users.id AS commenter_id, users.name AS commenter_name,
                                                 users.profile_pic AS commenter_profile_pic
                                          FROM comments
                                          JOIN users ON comments.user_id = users.id
                                          WHERE comments.post_id = ?
                                          ORDER BY comments.created_at ASC");
mysqli_stmt_bind_param($comments_stmt, "i", $post_id);
mysqli_stmt_execute($comments_stmt);
$comments = mysqli_fetch_all(mysqli_stmt_get_result($comments_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($comments_stmt);
$comment_count = count($comments);

$dom_id = $is_shared_entry ? $post_id . '_s' . $row['sharer_id'] : (string) $post_id;
?>

<div class="post-card" style="animation-delay: <?php echo $delay; ?>s;">

    <?php if ($is_shared_entry): ?>
        <div class="shared-banner">
            🔁 <strong><?php echo htmlspecialchars($row['sharer_name']); ?></strong> shared this post
        </div>
    <?php endif; ?>

    <div class="post-header">
        <div class="post-avatar"><?php echo renderAvatar($row['author_name'], $row['author_profile_pic'] ?? null, 'post-avatar-img'); ?></div>
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
        <button class="post-action-btn comment-toggle-btn" onclick="toggleCommentBox('<?php echo $dom_id; ?>')">💬 Comment</button>
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
                    <div class="comment-avatar"><?php echo renderAvatar($c['commenter_name'], $c['commenter_profile_pic'] ?? null, 'comment-avatar-img'); ?></div>
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
            <div class="comment-avatar comment-avatar-self"><?php echo renderAvatar($_SESSION['user_name'], $_SESSION['profile_pic'] ?? null, 'comment-avatar-img'); ?></div>
            <input type="text" class="comment-input" id="commentInput-<?php echo $dom_id; ?>"
                   placeholder="Write a comment..."
                   onkeypress="if(event.key==='Enter'){submitComment(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>'); event.preventDefault();}">
            <button class="comment-send-btn" onclick="submitComment(<?php echo $post_id; ?>, '<?php echo $dom_id; ?>')">Send</button>
        </div>
    </div>

</div>
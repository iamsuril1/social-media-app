<?php
// This file expects $conn to already be available (included after db.php)

$current_user_id = currentUserId();
$per_page = 5;

$visible_ids = getVisibleUserIds($conn, $current_user_id);
$placeholders = implode(',', array_fill(0, count($visible_ids), '?'));
$types = str_repeat('i', count($visible_ids));

$sql = feedQuerySql($placeholders);
$limit_plus_one = $per_page + 1;
$all_types = $types . $types . "ii";
$all_params = array_merge($visible_ids, $visible_ids, [$limit_plus_one, 0]);

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$has_more = count($rows) > $per_page;
if ($has_more) {
    array_pop($rows);
}
?>

<div class="posts-feed" id="postsFeed">
    <?php if (empty($rows)): ?>

        <div class="feed-placeholder">
            <div class="placeholder-icon">
                <span class="dot dot-1"></span>
                <span class="dot dot-2"></span>
                <span class="dot dot-3"></span>
            </div>
            <p>Your feed is empty for now</p>
            <span class="placeholder-subtext">Add friends or follow people from the <a href="/social-media-app/friends/list.php">Friends page</a> to see their posts here.</span>
        </div>

    <?php else: ?>
        <?php $delay = 0; ?>
        <?php foreach ($rows as $row): ?>
            <?php include __DIR__ . '/post_card.php'; ?>
            <?php $delay += 0.08; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($has_more): ?>
    <div class="load-more-wrapper">
        <button class="btn-load-more" id="loadMoreBtn" onclick="loadMorePosts()">Load More</button>
    </div>
<?php endif; ?>

<script>
let currentOffset = <?php echo $per_page; ?>;

function loadMorePosts() {
    const btn = document.getElementById('loadMoreBtn');
    btn.textContent = 'Loading...';
    btn.disabled = true;

    fetch('/social-media-app/posts/load_more.php?offset=' + currentOffset)
        .then(r => r.json())
        .then(data => {
            document.getElementById('postsFeed').insertAdjacentHTML('beforeend', data.html);
            currentOffset += <?php echo $per_page; ?>;

            if (data.has_more) {
                btn.textContent = 'Load More';
                btn.disabled = false;
            } else {
                btn.remove();
            }
        })
        .catch(() => {
            btn.textContent = 'Load More';
            btn.disabled = false;
        });
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
            if (data.shared) {
                setTimeout(() => location.reload(), 500);
            }
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
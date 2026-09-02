<?php
$pageTitle = "Group";
$pageCss = "groups.css";
$extraCss = ["feed.css"];
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$group_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$flash = getFlash();

if ($group_id <= 0) {
    setFlash("Invalid group.");
    redirect('/social-media-app/groups/view.php');
}

// Fetch group info
$group_stmt = mysqli_prepare($conn, "SELECT id, name, description, created_by, created_at FROM `groups` WHERE id = ?");
mysqli_stmt_bind_param($group_stmt, "i", $group_id);
mysqli_stmt_execute($group_stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($group_stmt));
mysqli_stmt_close($group_stmt);

if (!$group) {
    setFlash("Group not found.");
    redirect('/social-media-app/groups/view.php');
}

// Am I a member? What's my role?
$member_stmt = mysqli_prepare($conn, "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($member_stmt, "ii", $group_id, $user_id);
mysqli_stmt_execute($member_stmt);
$member_row = mysqli_fetch_assoc(mysqli_stmt_get_result($member_stmt));
mysqli_stmt_close($member_stmt);

$is_member = (bool) $member_row;
$is_admin = $is_member && $member_row['role'] === 'admin';

// Member list
$members_stmt = mysqli_prepare($conn, "SELECT users.id, users.name, users.profile_pic, group_members.role
                                        FROM group_members
                                        JOIN users ON group_members.user_id = users.id
                                        WHERE group_members.group_id = ?
                                        ORDER BY group_members.role = 'admin' DESC, users.name ASC");
mysqli_stmt_bind_param($members_stmt, "i", $group_id);
mysqli_stmt_execute($members_stmt);
$members = mysqli_fetch_all(mysqli_stmt_get_result($members_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($members_stmt);

// Group posts (only fetched/shown if the user is a member)
$group_posts = [];
if ($is_member) {
    $posts_stmt = mysqli_prepare($conn, "SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
                                                 users.id AS author_id, users.name AS author_name, users.profile_pic AS author_profile_pic,
                                                 NULL AS sharer_id, NULL AS sharer_name
                                          FROM posts
                                          JOIN users ON posts.user_id = users.id
                                          WHERE posts.group_id = ?
                                          ORDER BY posts.created_at DESC");
    mysqli_stmt_bind_param($posts_stmt, "i", $group_id);
    mysqli_stmt_execute($posts_stmt);
    $group_posts = mysqli_fetch_all(mysqli_stmt_get_result($posts_stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($posts_stmt);
}
?>

<?php if ($flash): ?>
    <div class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="group-header-card">
    <div class="group-cover"></div>

    <div class="group-info-row">
        <div class="group-icon-large"><?php echo strtoupper(substr($group['name'], 0, 1)); ?></div>

        <div class="group-details">
            <h2 class="group-title"><?php echo htmlspecialchars($group['name']); ?></h2>
            <p class="group-description"><?php echo !empty($group['description']) ? nl2br(htmlspecialchars($group['description'])) : '<span class="no-bio">No description yet.</span>'; ?></p>
            <p class="group-member-count"><?php echo count($members); ?> <?php echo count($members) == 1 ? 'member' : 'members'; ?> · Created <?php echo date("F Y", strtotime($group['created_at'])); ?></p>
        </div>

        <div class="group-actions">
            <?php if ($is_member): ?>
                <?php if ($is_admin): ?>
                    <span class="btn-group-action btn-admin-badge"><span class="btn-icon">👑</span> Admin</span>
                    <a href="/social-media-app/groups/delete.php?id=<?php echo $group_id; ?>"
                       class="btn-group-action btn-delete-group"
                       onclick="return confirm('Delete this group permanently? All posts and members will be removed. This cannot be undone.');">
                       <span class="btn-icon">🗑</span> Delete Group
                    </a>
                <?php endif; ?>
                <button class="btn-group-action btn-leave-group" onclick="leaveGroup(<?php echo $group_id; ?>, <?php echo $is_admin ? 'true' : 'false'; ?>)">
                    Leave Group
                </button>
            <?php else: ?>
                <button class="btn-group-action btn-join-group" onclick="joinGroup(<?php echo $group_id; ?>)">
                    <span class="btn-icon">＋</span> Join Group
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($is_member): ?>
    <div class="group-form-card">
        <h2>Members</h2>
        <div class="group-member-list">
            <?php foreach ($members as $member): ?>
                <div class="group-member-row-wrapper">
                    <a href="/social-media-app/profile/view.php?id=<?php echo $member['id']; ?>" class="group-member-row">
                        <div class="group-member-avatar"><?php echo renderAvatar($member['name'], $member['profile_pic'], 'group-member-avatar-img'); ?></div>
                        <span class="group-member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                        <?php if ($member['role'] === 'admin'): ?>
                            <span class="member-role-tag">Admin</span>
                        <?php endif; ?>
                    </a>
                    <?php if ($is_admin && $member['id'] != $user_id): ?>
                        <button class="btn-remove-member" onclick="removeMember(<?php echo $group_id; ?>, <?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?>')">Remove</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="group-post-form-card">
        <form method="POST" action="/social-media-app/groups/create_post.php" enctype="multipart/form-data">
            <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
            <textarea name="content" rows="3" placeholder="Share something with the group..."></textarea>

            <div class="group-post-form-footer">
                <label for="groupPostImage" class="btn-attach-image">📷 Photo</label>
                <input type="file" name="image" id="groupPostImage" accept="image/*" style="display:none;" onchange="this.form.querySelector('.attach-filename').textContent = this.files[0]?.name || '';">
                <span class="attach-filename"></span>
                <button type="submit" class="btn-post-small">Post</button>
            </div>
        </form>
    </div>

    <div class="posts-feed">
        <?php if (empty($group_posts)): ?>
            <div class="feed-placeholder">
                <p>No posts in this group yet — be the first to share something!</p>
            </div>
        <?php else: ?>
            <?php $delay = 0; ?>
            <?php foreach ($group_posts as $row): ?>
                <?php include __DIR__ . '/../posts/post_card.php'; ?>
                <?php $delay += 0.06; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="group-form-card">
        <p class="empty-text">Join this group to see its members and posts.</p>
    </div>
<?php endif; ?>

<script>
function joinGroup(groupId) {
    fetch('/social-media-app/groups/join.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'group_id=' + encodeURIComponent(groupId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Could not join group.');
        }
    });
}

function leaveGroup(groupId, isAdmin) {
    if (isAdmin) {
        if (!confirm('You are the admin of this group. Leaving may transfer admin rights or affect the group. Continue?')) return;
    } else {
        if (!confirm('Leave this group?')) return;
    }

    fetch('/social-media-app/groups/leave.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'group_id=' + encodeURIComponent(groupId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/social-media-app/groups/view.php';
        } else {
            alert(data.message || 'Could not leave group.');
        }
    });
}

function removeMember(groupId, targetUserId, targetName) {
    if (!confirm('Remove ' + targetName + ' from this group?')) return;

    fetch('/social-media-app/groups/remove_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'group_id=' + encodeURIComponent(groupId) + '&user_id=' + encodeURIComponent(targetUserId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Could not remove member.');
        }
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
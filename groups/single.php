<?php
$pageTitle = "Group";
$pageCss = "groups.css";
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
                <a href="/social-media-app/profile/view.php?id=<?php echo $member['id']; ?>" class="group-member-row">
                    <div class="group-member-avatar"><?php echo renderAvatar($member['name'], $member['profile_pic'], 'group-member-avatar-img'); ?></div>
                    <span class="group-member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                    <?php if ($member['role'] === 'admin'): ?>
                        <span class="member-role-tag">Admin</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="group-form-card">
        <p class="empty-text">Group posts are coming soon — you'll be able to post here just like the main feed.</p>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
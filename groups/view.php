<?php
$pageTitle = "Groups";
$pageCss = "groups.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$flash = getFlash();

$my_groups_stmt = mysqli_prepare($conn, "SELECT g.id, g.name, g.description, g.created_by,
                                                 (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count
                                          FROM `groups` g
                                          JOIN group_members gm ON gm.group_id = g.id
                                          WHERE gm.user_id = ?
                                          ORDER BY g.created_at DESC");
mysqli_stmt_bind_param($my_groups_stmt, "i", $user_id);
mysqli_stmt_execute($my_groups_stmt);
$my_groups = mysqli_fetch_all(mysqli_stmt_get_result($my_groups_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($my_groups_stmt);

$my_group_ids = array_column($my_groups, 'id');

if (!empty($my_group_ids)) {
    $placeholders = implode(',', array_fill(0, count($my_group_ids), '?'));
    $types = str_repeat('i', count($my_group_ids));
    $discover_sql = "SELECT g.id, g.name, g.description,
                             (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count
                      FROM `groups` g
                      WHERE g.id NOT IN ($placeholders)
                      ORDER BY g.created_at DESC LIMIT 20";
    $discover_stmt = mysqli_prepare($conn, $discover_sql);
    mysqli_stmt_bind_param($discover_stmt, $types, ...$my_group_ids);
} else {
    $discover_stmt = mysqli_prepare($conn, "SELECT g.id, g.name, g.description,
                                                    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count
                                             FROM `groups` g
                                             ORDER BY g.created_at DESC LIMIT 20");
}
mysqli_stmt_execute($discover_stmt);
$discover_groups = mysqli_fetch_all(mysqli_stmt_get_result($discover_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($discover_stmt);
?>

<?php if ($flash): ?>
    <div class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="groups-header-row">
    <h2>Groups</h2>
    <a href="/social-media-app/groups/create.php" class="btn-create-group">+ Create Group</a>
</div>

<div class="group-form-card">
    <h2>My Groups <span class="count-badge"><?php echo count($my_groups); ?></span></h2>

    <?php if (empty($my_groups)): ?>
        <p class="empty-text">You haven't joined any groups yet. Check the suggestions below!</p>
    <?php else: ?>
        <div class="group-list">
            <?php foreach ($my_groups as $group): ?>
                <a href="/social-media-app/groups/single.php?id=<?php echo $group['id']; ?>" class="group-row">
                    <div class="group-icon"><?php echo strtoupper(substr($group['name'], 0, 1)); ?></div>
                    <div class="group-info">
                        <span class="group-name"><?php echo htmlspecialchars($group['name']); ?></span>
                        <span class="group-meta"><?php echo $group['member_count']; ?> <?php echo $group['member_count'] == 1 ? 'member' : 'members'; ?></span>
                    </div>
                    <?php if ($group['created_by'] == $user_id): ?>
                        <span class="group-admin-tag">Admin</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="group-form-card">
    <h2>Discover Groups</h2>

    <?php if (empty($discover_groups)): ?>
        <p class="empty-text">No new groups to show right now.</p>
    <?php else: ?>
        <div class="group-list">
            <?php foreach ($discover_groups as $group): ?>
                <a href="/social-media-app/groups/single.php?id=<?php echo $group['id']; ?>" class="group-row">
                    <div class="group-icon discover"><?php echo strtoupper(substr($group['name'], 0, 1)); ?></div>
                    <div class="group-info">
                        <span class="group-name"><?php echo htmlspecialchars($group['name']); ?></span>
                        <span class="group-meta"><?php echo $group['member_count']; ?> <?php echo $group['member_count'] == 1 ? 'member' : 'members'; ?></span>
                    </div>
                    <span class="btn-view-group">View</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
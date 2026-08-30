<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group.']);
    exit();
}

// Confirm membership
$check_member = mysqli_prepare($conn, "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_member, "ii", $group_id, $user_id);
mysqli_stmt_execute($check_member);
$member = mysqli_fetch_assoc(mysqli_stmt_get_result($check_member));
mysqli_stmt_close($check_member);

if (!$member) {
    echo json_encode(['success' => false, 'message' => 'You are not a member of this group.']);
    exit();
}

// If this user is the admin, check if there are other members to hand admin to
if ($member['role'] === 'admin') {
    $others_stmt = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id != ? ORDER BY joined_at ASC LIMIT 1");
    mysqli_stmt_bind_param($others_stmt, "ii", $group_id, $user_id);
    mysqli_stmt_execute($others_stmt);
    $next_member = mysqli_fetch_assoc(mysqli_stmt_get_result($others_stmt));
    mysqli_stmt_close($others_stmt);

    if ($next_member) {
        // Promote the longest-standing remaining member to admin
        $promote_stmt = mysqli_prepare($conn, "UPDATE group_members SET role = 'admin' WHERE id = ?");
        mysqli_stmt_bind_param($promote_stmt, "i", $next_member['id']);
        mysqli_stmt_execute($promote_stmt);
        mysqli_stmt_close($promote_stmt);
    } else {
        // No other members — delete the group entirely
        $delete_group_stmt = mysqli_prepare($conn, "DELETE FROM `groups` WHERE id = ?");
        mysqli_stmt_bind_param($delete_group_stmt, "i", $group_id);
        mysqli_stmt_execute($delete_group_stmt);
        mysqli_stmt_close($delete_group_stmt);
        // group_members row cascades automatically since group_id has ON DELETE CASCADE

        echo json_encode(['success' => true, 'group_deleted' => true]);
        exit();
    }
}

// Remove this user from the group
$stmt = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
} else {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Something went wrong.']);
}
exit();
?>
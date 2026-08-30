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

// Confirm group exists
$check_group = mysqli_prepare($conn, "SELECT id FROM `groups` WHERE id = ?");
mysqli_stmt_bind_param($check_group, "i", $group_id);
mysqli_stmt_execute($check_group);
mysqli_stmt_store_result($check_group);

if (mysqli_stmt_num_rows($check_group) === 0) {
    mysqli_stmt_close($check_group);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit();
}
mysqli_stmt_close($check_group);

// Check if already a member
$check_member = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_member, "ii", $group_id, $user_id);
mysqli_stmt_execute($check_member);
mysqli_stmt_store_result($check_member);

if (mysqli_stmt_num_rows($check_member) > 0) {
    mysqli_stmt_close($check_member);
    echo json_encode(['success' => false, 'message' => 'You are already a member of this group.']);
    exit();
}
mysqli_stmt_close($check_member);

$stmt = mysqli_prepare($conn, "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
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
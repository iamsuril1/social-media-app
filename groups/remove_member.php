<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
$target_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($group_id <= 0 || $target_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

if ($target_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'Use "Leave Group" to remove yourself.']);
    exit();
}

$admin_check = mysqli_prepare($conn, "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($admin_check, "ii", $group_id, $user_id);
mysqli_stmt_execute($admin_check);
$requester = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_check));
mysqli_stmt_close($admin_check);

if (!$requester || $requester['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only group admins can remove members.']);
    exit();
}

$target_check = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($target_check, "ii", $group_id, $target_id);
mysqli_stmt_execute($target_check);
mysqli_stmt_store_result($target_check);

if (mysqli_stmt_num_rows($target_check) === 0) {
    mysqli_stmt_close($target_check);
    echo json_encode(['success' => false, 'message' => 'That user is not a member of this group.']);
    exit();
}
mysqli_stmt_close($target_check);

$stmt = mysqli_prepare($conn, "DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $group_id, $target_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);
} else {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Something went wrong.']);
}
exit();
?>
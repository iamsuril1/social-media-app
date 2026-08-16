<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$target_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

if ($target_id <= 0 || $target_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

=$check_user = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
mysqli_stmt_bind_param($check_user, "i", $target_id);
mysqli_stmt_execute($check_user);
mysqli_stmt_store_result($check_user);

if (mysqli_stmt_num_rows($check_user) === 0) {
    mysqli_stmt_close($check_user);
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit();
}
mysqli_stmt_close($check_user);

=$stmt = mysqli_prepare($conn, "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$already_following = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($already_following) {
    $stmt = mysqli_prepare($conn, "DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $following = false;
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $following = true;
}

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM follows WHERE following_id = ?");
mysqli_stmt_bind_param($count_stmt, "i", $target_id);
mysqli_stmt_execute($count_stmt);
$total_followers = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);

echo json_encode([
    'success' => true,
    'following' => $following,
    'total_followers' => (int) $total_followers
]);
exit();
?>
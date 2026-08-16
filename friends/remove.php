<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$target_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($target_id <= 0) {
    setFlash("Invalid request.");
    redirect('/social-media-app/friends/list.php');
}

$stmt = mysqli_prepare($conn, "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
mysqli_stmt_bind_param($stmt, "iiii", $user_id, $target_id, $target_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    setFlash("Friend removed.");
} else {
    setFlash("Something went wrong.");
}
mysqli_stmt_close($stmt);

redirect('/social-media-app/friends/list.php');
?>
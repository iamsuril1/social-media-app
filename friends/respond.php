<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$sender_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($sender_id <= 0 || !in_array($action, ['accept', 'reject'])) {
    setFlash("Invalid request.");
    redirect('/social-media-app/friends/list.php');
}

// Confirm the pending request actually exists and was sent TO the current user
$stmt = mysqli_prepare($conn, "SELECT id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($stmt, "ii", $sender_id, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) === 0) {
    mysqli_stmt_close($stmt);
    setFlash("Friend request not found.");
    redirect('/social-media-app/friends/list.php');
}
mysqli_stmt_close($stmt);

if ($action === 'accept') {
    $stmt = mysqli_prepare($conn, "UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $sender_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    setFlash("Friend request accepted!");
} else {
    $stmt = mysqli_prepare($conn, "DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "ii", $sender_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    setFlash("Friend request declined.");
}

redirect('/social-media-app/friends/list.php');
?>
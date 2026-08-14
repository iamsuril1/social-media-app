<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$target_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($target_id <= 0 || $target_id == $user_id) {
    setFlash("Invalid request.");
    redirect('/social-media-app/friends/list.php');
}

$check_user = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
mysqli_stmt_bind_param($check_user, "i", $target_id);
mysqli_stmt_execute($check_user);
mysqli_stmt_store_result($check_user);

if (mysqli_stmt_num_rows($check_user) === 0) {
    mysqli_stmt_close($check_user);
    setFlash("User not found.");
    redirect('/social-media-app/friends/list.php');
}
mysqli_stmt_close($check_user);

$check = mysqli_prepare($conn, "SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
mysqli_stmt_bind_param($check, "iiii", $user_id, $target_id, $target_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    mysqli_stmt_close($check);
    setFlash("A friend request already exists with this user.");
    redirect('/social-media-app/friends/list.php');
}
mysqli_stmt_close($check);

$stmt = mysqli_prepare($conn, "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $target_id);

if (mysqli_stmt_execute($stmt)) {
    setFlash("Friend request sent!");
} else {
    setFlash("Something went wrong. Please try again.");
}
mysqli_stmt_close($stmt);

redirect('/social-media-app/friends/list.php');
?>
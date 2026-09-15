<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$group_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($group_id <= 0) {
    setFlash("Invalid group.");
    redirect('/social-media-app/groups/view.php');
}

$admin_check = mysqli_prepare($conn, "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($admin_check, "ii", $group_id, $user_id);
mysqli_stmt_execute($admin_check);
$member = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_check));
mysqli_stmt_close($admin_check);

if (!$member || $member['role'] !== 'admin') {
    setFlash("Only the group admin can delete this group.");
    redirect('/social-media-app/groups/single.php?id=' . $group_id);
}

$posts_stmt = mysqli_prepare($conn, "SELECT image FROM posts WHERE group_id = ? AND image IS NOT NULL");
mysqli_stmt_bind_param($posts_stmt, "i", $group_id);
mysqli_stmt_execute($posts_stmt);
$group_post_images = mysqli_fetch_all(mysqli_stmt_get_result($posts_stmt), MYSQLI_ASSOC);
mysqli_stmt_close($posts_stmt);

foreach ($group_post_images as $post) {
    $image_path = __DIR__ . '/../assets/uploads/posts/' . $post['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

$stmt = mysqli_prepare($conn, "DELETE FROM `groups` WHERE id = ? AND created_by = ?");
mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    setFlash("Group deleted.");
} else {
    setFlash("Something went wrong, or you're not the original creator of this group.");
}
mysqli_stmt_close($stmt);

redirect('/social-media-app/groups/view.php');
?>
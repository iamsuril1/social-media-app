<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($post_id <= 0) {
    setFlash("Invalid post.");
    redirect('/social-media-app/index.php');
}

// Fetch the post first to check ownership and get the image filename
$stmt = mysqli_prepare($conn, "SELECT user_id, image FROM posts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$post) {
    setFlash("Post not found.");
    redirect('/social-media-app/index.php');
}

if ($post['user_id'] != $user_id) {
    setFlash("You don't have permission to delete this post.");
    redirect('/social-media-app/index.php');
}

if (!empty($post['image'])) {
    $image_path = __DIR__ . '/../assets/uploads/posts/' . $post['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}


$stmt = mysqli_prepare($conn, "DELETE FROM posts WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);

if (mysqli_stmt_execute($stmt)) {
    setFlash("Post deleted.");
} else {
    setFlash("Something went wrong while deleting the post.");
}
mysqli_stmt_close($stmt);

redirect('/social-media-app/index.php');
?>
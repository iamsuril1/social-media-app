<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post.']);
    exit();
}

$check_post = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ?");
mysqli_stmt_bind_param($check_post, "i", $post_id);
mysqli_stmt_execute($check_post);
mysqli_stmt_store_result($check_post);

if (mysqli_stmt_num_rows($check_post) === 0) {
    mysqli_stmt_close($check_post);
    echo json_encode(['success' => false, 'message' => 'Post not found.']);
    exit();
}
mysqli_stmt_close($check_post);

$stmt = mysqli_prepare($conn, "SELECT id FROM shares WHERE post_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$already_shared = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($already_shared) {

    $stmt = mysqli_prepare($conn, "DELETE FROM shares WHERE post_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $shared = false;
} else {
   
    $stmt = mysqli_prepare($conn, "INSERT INTO shares (post_id, user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $post_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $shared = true;
}

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM shares WHERE post_id = ?");
mysqli_stmt_bind_param($count_stmt, "i", $post_id);
mysqli_stmt_execute($count_stmt);
$total_shares = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);

echo json_encode([
    'success' => true,
    'shared' => $shared,
    'total_shares' => (int) $total_shares
]);
exit();
?>
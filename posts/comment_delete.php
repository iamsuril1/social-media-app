<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

if ($comment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid comment.']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id, post_id FROM comments WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $comment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$comment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$comment) {
    echo json_encode(['success' => false, 'message' => 'Comment not found.']);
    exit();
}

if ($comment['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'You can only delete your own comments.']);
    exit();
}

$post_id = $comment['post_id'];

$stmt = mysqli_prepare($conn, "DELETE FROM comments WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $comment_id, $user_id);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Something went wrong.']);
    exit();
}
mysqli_stmt_close($stmt);

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM comments WHERE post_id = ?");
mysqli_stmt_bind_param($count_stmt, "i", $post_id);
mysqli_stmt_execute($count_stmt);
$total_comments = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);

echo json_encode([
    'success' => true,
    'total_comments' => (int) $total_comments
]);
exit();
?>
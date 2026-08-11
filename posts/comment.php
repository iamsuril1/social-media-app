<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
$comment_text = isset($_POST['comment']) ? sanitize($_POST['comment']) : '';

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post.']);
    exit();
}

if (empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
    exit();
}

if (strlen($comment_text) > 500) {
    echo json_encode(['success' => false, 'message' => 'Comment is too long (max 500 characters).']);
    exit();
}

// Confirm the post exists
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

// Insert the comment
$stmt = mysqli_prepare($conn, "INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $post_id, $user_id, $comment_text);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    exit();
}

$comment_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// Get the commenter's name for the response
$user_stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_name = mysqli_fetch_assoc($user_result)['name'];
mysqli_stmt_close($user_stmt);

// Get updated total comment count for this post
$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM comments WHERE post_id = ?");
mysqli_stmt_bind_param($count_stmt, "i", $post_id);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_comments = mysqli_fetch_assoc($count_result)['total'];
mysqli_stmt_close($count_stmt);

echo json_encode([
    'success' => true,
    'comment_id' => $comment_id,
    'user_id' => $user_id,
    'user_name' => $user_name,
    'comment' => $comment_text,
    'total_comments' => (int) $total_comments
]);
exit();
?>
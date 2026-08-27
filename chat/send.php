<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$friend_id = isset($_POST['friend_id']) ? (int) $_POST['friend_id'] : 0;
$message_text = isset($_POST['message']) ? sanitize($_POST['message']) : '';

if ($friend_id <= 0 || $friend_id == $user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation.']);
    exit();
}

if (empty($message_text)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
    exit();
}

if (strlen($message_text) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long.']);
    exit();
}

$friend_check = mysqli_prepare($conn, "SELECT id FROM friends 
                                        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
                                        AND status = 'accepted'");
mysqli_stmt_bind_param($friend_check, "iiii", $user_id, $friend_id, $friend_id, $user_id);
mysqli_stmt_execute($friend_check);
mysqli_stmt_store_result($friend_check);

if (mysqli_stmt_num_rows($friend_check) === 0) {
    mysqli_stmt_close($friend_check);
    echo json_encode(['success' => false, 'forbidden' => true, 'message' => 'You are no longer friends with this user.']);
    exit();
}
mysqli_stmt_close($friend_check);

$stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $user_id, $friend_id, $message_text);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => false, 'message' => 'Something went wrong.']);
    exit();
}

$message_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'message_id' => $message_id,
    'message' => $message_text,
    'time' => date("g:i A")
]);
exit();
?>
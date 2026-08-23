<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$user_id = currentUserId();
$friend_id = isset($_GET['friend_id']) ? (int) $_GET['friend_id'] : 0;
$after_id = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;

if ($friend_id <= 0 || $friend_id == $user_id) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit();
}

// Enforce: only accepted friends can view each other's messages
$friend_check = mysqli_prepare($conn, "SELECT id FROM friends 
                                        WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) 
                                        AND status = 'accepted'");
mysqli_stmt_bind_param($friend_check, "iiii", $user_id, $friend_id, $friend_id, $user_id);
mysqli_stmt_execute($friend_check);
mysqli_stmt_store_result($friend_check);

if (mysqli_stmt_num_rows($friend_check) === 0) {
    mysqli_stmt_close($friend_check);
    echo json_encode(['success' => false, 'messages' => []]);
    exit();
}
mysqli_stmt_close($friend_check);

// Fetch any messages newer than $after_id in this conversation
$stmt = mysqli_prepare($conn, "SELECT id, sender_id, message, created_at
                                FROM messages
                                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                                AND id > ?
                                ORDER BY created_at ASC");
mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $friend_id, $friend_id, $user_id, $after_id);
mysqli_stmt_execute($stmt);
$new_messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Mark any newly-fetched messages FROM the friend as read
$mark_read_stmt = mysqli_prepare($conn, "UPDATE messages SET is_read = 1 
                                          WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND id > ?");
mysqli_stmt_bind_param($mark_read_stmt, "iii", $friend_id, $user_id, $after_id);
mysqli_stmt_execute($mark_read_stmt);
mysqli_stmt_close($mark_read_stmt);

$formatted = array_map(function($msg) use ($user_id) {
    return [
        'id' => (int) $msg['id'],
        'is_mine' => $msg['sender_id'] == $user_id,
        'message' => $msg['message'],
        'time' => date("g:i A", strtotime($msg['created_at']))
    ];
}, $new_messages);

echo json_encode(['success' => true, 'messages' => $formatted]);
exit();
?>
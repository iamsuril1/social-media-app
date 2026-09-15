<?php
session_start();

function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /social-media-app/auth/login.php");
        exit();
    }
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function redirect($path) {
    header("Location: " . $path);
    exit();
}

function setFlash($message) {
    $_SESSION['flash'] = $message;
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $msg;
    }
    return null;
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

function getVisibleUserIds($conn, $user_id) {
    $ids = [(int) $user_id];

    $stmt = mysqli_prepare($conn, "SELECT IF(user_id = ?, friend_id, user_id) AS friend_uid 
                                    FROM friends 
                                    WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'");
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (int) $row['friend_uid'];
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT following_id FROM follows WHERE follower_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (int) $row['following_id'];
    }
    mysqli_stmt_close($stmt);

    return array_values(array_unique($ids));
}


function feedQuerySql($placeholders) {
    return "
        (SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
                author.id AS author_id, author.name AS author_name, author.profile_pic AS author_profile_pic,
                NULL AS sharer_id, NULL AS sharer_name, posts.created_at AS sort_time
         FROM posts
         JOIN users AS author ON posts.user_id = author.id
         WHERE posts.user_id IN ($placeholders))

        UNION ALL

        (SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
                author.id AS author_id, author.name AS author_name, author.profile_pic AS author_profile_pic,
                sharer.id AS sharer_id, sharer.name AS sharer_name, shares.created_at AS sort_time
         FROM shares
         JOIN posts ON shares.post_id = posts.id
         JOIN users AS author ON posts.user_id = author.id
         JOIN users AS sharer ON shares.user_id = sharer.id
         WHERE shares.user_id IN ($placeholders))

        ORDER BY sort_time DESC
        LIMIT ? OFFSET ?
    ";
}

function renderAvatar($name, $profile_pic, $extraClass = '') {
    if (!empty($profile_pic)) {
        $src = '/social-media-app/assets/uploads/profile/' . htmlspecialchars($profile_pic);
        return '<img src="' . $src . '" class="avatar-img ' . htmlspecialchars($extraClass) . '" alt="' . htmlspecialchars($name) . '">';
    }
    $initial = strtoupper(substr($name, 0, 1));
    return '<div class="avatar-initial ' . htmlspecialchars($extraClass) . '">' . $initial . '</div>';
}

function getUnreadMessageCount($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM messages WHERE receiver_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
    mysqli_stmt_close($stmt);
    return (int) $total;
}

function createNotification($conn, $user_id, $actor_id, $type, $reference_id = null) {
    if ($user_id == $actor_id) {
        return; 
    }
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iisi", $user_id, $actor_id, $type, $reference_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getUnreadNotificationCount($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
    mysqli_stmt_close($stmt);
    return (int) $total;
}

function formatNotification($notif) {
    $name = htmlspecialchars($notif['actor_name']);

    switch ($notif['type']) {
        case 'like':
            return ['text' => "$name liked your post", 'link' => "/social-media-app/index.php#post-{$notif['reference_id']}"];
        case 'comment':
            return ['text' => "$name commented on your post", 'link' => "/social-media-app/index.php#post-{$notif['reference_id']}"];
        case 'share':
            return ['text' => "$name shared your post", 'link' => "/social-media-app/index.php#post-{$notif['reference_id']}"];
        case 'friend_request':
            return ['text' => "$name sent you a friend request", 'link' => "/social-media-app/friends/list.php"];
        case 'friend_accept':
            return ['text' => "$name accepted your friend request", 'link' => "/social-media-app/profile/view.php?id={$notif['actor_id']}"];
        case 'follow':
            return ['text' => "$name started following you", 'link' => "/social-media-app/profile/view.php?id={$notif['actor_id']}"];
        case 'group_join':
            return ['text' => "$name joined your group", 'link' => "/social-media-app/groups/single.php?id={$notif['reference_id']}"];
        default:
            return ['text' => "New notification", 'link' => "#"];
    }
}
?>
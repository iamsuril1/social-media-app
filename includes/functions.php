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
// Returns an array of user IDs whose posts should appear in the current user's feed:
// themselves, their accepted friends, and everyone they follow
function getVisibleUserIds($conn, $user_id) {
    $ids = [(int) $user_id];

    // Friends (accepted, either direction)
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
                author.id AS author_id, author.name AS author_name,
                NULL AS sharer_id, NULL AS sharer_name, posts.created_at AS sort_time
         FROM posts
         JOIN users AS author ON posts.user_id = author.id
         WHERE posts.user_id IN ($placeholders))

        UNION ALL

        (SELECT posts.id AS post_id, posts.content, posts.image, posts.created_at AS post_created_at,
                author.id AS author_id, author.name AS author_name,
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
?>
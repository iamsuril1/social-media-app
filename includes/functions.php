<?php
session_start();

// Clean user input to prevent XSS/HTML injection
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Check if a user is currently logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Force login before accessing a page
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /social-media-app/auth/login.php");
        exit();
    }
}

// Get the currently logged-in user's ID
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Simple redirect helper
function redirect($path) {
    header("Location: " . $path);
    exit();
}

// Show a flash message once, then clear it
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

// Convert a MySQL datetime into a human-friendly "time ago" string
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

    // People this user follows
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

// Builds the feed SQL: original posts authored by, or shared by, anyone in $placeholders.
// Same set of ids is bound twice (once per subquery) by the caller.
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

// Renders an avatar — uses the uploaded profile picture if one exists, 
// otherwise falls back to a colored circle with the user's first initial
function renderAvatar($name, $profile_pic, $extraClass = '') {
    if (!empty($profile_pic)) {
        $src = '/social-media-app/assets/uploads/profile/' . htmlspecialchars($profile_pic);
        return '<img src="' . $src . '" class="avatar-img ' . htmlspecialchars($extraClass) . '" alt="' . htmlspecialchars($name) . '">';
    }
    $initial = strtoupper(substr($name, 0, 1));
    return '<div class="avatar-initial ' . htmlspecialchars($extraClass) . '">' . $initial . '</div>';
}
?>
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
?>
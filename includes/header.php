<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(); // every page that includes header.php is auto-protected
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . " - SocialApp" : "SocialApp"; ?></title>
    <link rel="stylesheet" href="/social-media-app/assets/css/navbar.css">
    <?php if (isset($pageCss)): ?>
        <link rel="stylesheet" href="/social-media-app/assets/css/<?php echo htmlspecialchars($pageCss); ?>">
    <?php endif; ?>
    <?php if (isset($extraCss) && is_array($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="/social-media-app/assets/css/<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<nav class="navbar">
    <a href="/social-media-app/index.php" class="navbar-logo">
        <span class="logo-icon">S</span>
        <span>SocialApp</span>
    </a>

    <div class="navbar-links">
        <a href="/social-media-app/index.php">Feed</a>
        <a href="/social-media-app/friends/list.php">Friends</a>
        <a href="/social-media-app/chat/inbox.php">Chat</a>
        <a href="/social-media-app/groups/view.php">Groups</a>
        <a href="/social-media-app/profile/view.php">Profile</a>
    </div>

    <div class="navbar-user">
        <a href="/social-media-app/profile/view.php" class="navbar-avatar-link">
            <?php echo renderAvatar($_SESSION['user_name'], $_SESSION['profile_pic'] ?? null, 'navbar-avatar'); ?>
        </a>
        <span class="navbar-username"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="/social-media-app/auth/logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<main class="page-content">
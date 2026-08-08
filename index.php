<?php
$pageTitle = "Feed";
$pageCss = "feed.css";
require_once __DIR__ . '/includes/header.php';
?>

<div class="welcome-banner">
    <div class="welcome-text">
        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?> <span class="wave">👋</span></h2>
        <p>Here's what your friends and the people you follow have been posting.</p>
    </div>
</div>

<a href="/social-media-app/posts/create.php" class="quick-post-box">
    <div class="quick-post-avatar"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></div>
    <span class="quick-post-placeholder">What's on your mind, <?php echo htmlspecialchars($_SESSION['user_name']); ?>?</span>
</a>

<?php require_once __DIR__ . '/posts/feed.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
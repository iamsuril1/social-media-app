<?php
$pageTitle = "Feed";
$pageCss = "feed.css";
require_once __DIR__ . '/includes/header.php';
?>

<div class="welcome-banner">
    <div class="welcome-text">
        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?> </h2>
        <p>Here's what your friends and the people you follow have been posting.</p>
    </div>
</div>

<div class="feed-placeholder">
    <div class="placeholder-icon">
        <span class="dot dot-1"></span>
        <span class="dot dot-2"></span>
        <span class="dot dot-3"></span>
    </div>
    <p>Your feed is empty for now</p>
    <span class="placeholder-subtext">Post creation and the friends/following feed logic are coming in the next few days.</span>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
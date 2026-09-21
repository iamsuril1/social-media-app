<?php
$pageTitle = "Search";
$pageCss = "search.css";
require_once __DIR__ . '/../includes/header.php';

$current_user_id = currentUserId();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (!empty($query)) {
    $search_term = '%' . $query . '%';
    $stmt = mysqli_prepare($conn, "SELECT id, name, profile_pic, bio FROM users 
                                    WHERE name LIKE ? AND id != ?
                                    ORDER BY name ASC LIMIT 30");
    mysqli_stmt_bind_param($stmt, "si", $search_term, $current_user_id);
    mysqli_stmt_execute($stmt);
    $results = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}
?>

<div class="search-card">
    <h2>Search People</h2>
    <div class="accent-bar"></div>

    <form method="GET" action="" class="search-form">
        <input type="text" name="q" placeholder="Search by name..." value="<?php echo htmlspecialchars($query); ?>" autofocus>
        <button type="submit" class="btn-search">Search</button>
    </form>

    <?php if (!empty($query)): ?>
        <?php if (empty($results)): ?>
            <p class="empty-text">No users found matching "<?php echo htmlspecialchars($query); ?>"</p>
        <?php else: ?>
            <div class="search-results-list">
                <?php foreach ($results as $person): ?>
                    <a href="/social-media-app/profile/view.php?id=<?php echo $person['id']; ?>" class="search-result-row">
                        <div class="search-result-avatar"><?php echo renderAvatar($person['name'], $person['profile_pic'], 'search-avatar-img'); ?></div>
                        <div class="search-result-info">
                            <span class="search-result-name"><?php echo htmlspecialchars($person['name']); ?></span>
                            <?php if (!empty($person['bio'])): ?>
                                <span class="search-result-bio"><?php echo htmlspecialchars(mb_strimwidth($person['bio'], 0, 60, '...')); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
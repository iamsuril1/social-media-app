<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$current_user_id = currentUserId();
$per_page = 5;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

$visible_ids = getVisibleUserIds($conn, $current_user_id);
$placeholders = implode(',', array_fill(0, count($visible_ids), '?'));
$types = str_repeat('i', count($visible_ids));

$sql = feedQuerySql($placeholders);
$limit_plus_one = $per_page + 1;
$all_types = $types . $types . "ii";
$all_params = array_merge($visible_ids, $visible_ids, [$limit_plus_one, $offset]);

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
mysqli_stmt_execute($stmt);
$rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$has_more = count($rows) > $per_page;
if ($has_more) {
    array_pop($rows);
}

ob_start();
$delay = 0;
foreach ($rows as $row) {
    include __DIR__ . '/post_card.php';
    $delay += 0.08;
}
$html = ob_get_clean();

echo json_encode(['html' => $html, 'has_more' => $has_more]);
exit();
?>
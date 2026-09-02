<?php
/**
 * Automated end-to-end test runner for SocialApp.
 *
 * HOW TO RUN:
 *   1. Make sure Apache + MySQL are running in XAMPP.
 *   2. Open Command Prompt.
 *   3. Run:  php C:\xampp\htdocs\social-media-app\tests\run_tests.php
 *
 * This script drives the REAL app over HTTP (like a browser would),
 * using two separate cookie jars to simulate two logged-in users (A and B),
 * then checks the database directly to confirm the expected result.
 *
 * It creates its own throwaway test accounts (unique email each run) so it
 * never touches your real data, and cleans them up at the end.
 */

// ===================== CONFIG =====================
define('BASE_URL', 'http://localhost/social-media-app');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'social_media');
// ====================================================

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("DB connection failed: " . mysqli_connect_error() . "\n");
}

$pass = 0;
$fail = 0;
$failures = [];

function check($label, $condition) {
    global $pass, $fail, $failures;
    if ($condition) {
        $pass++;
        echo "  [PASS] $label\n";
    } else {
        $fail++;
        $failures[] = $label;
        echo "  [FAIL] $label\n";
    }
}

function section($title) {
    echo "\n=== $title ===\n";
}

// ---- Simple cURL helper with per-user cookie jar ----
function req($method, $path, $cookieFile, $fields = null, $isMultipart = false) {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); // array = multipart
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields ?? []));
        }
    }

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "  cURL error on $path: $err\n";
    }
    return $response;
}

function reqJson($method, $path, $cookieFile, $fields = null) {
    $raw = req($method, $path, $cookieFile, $fields);
    $data = json_decode($raw, true);
    return $data ?? ['_raw' => $raw];
}

// ---- Cookie jars for two independent sessions ----
$cookieA = tempnam(sys_get_temp_dir(), 'cookieA_');
$cookieB = tempnam(sys_get_temp_dir(), 'cookieB_');

$stamp = time();
$emailA = "testA_{$stamp}@example.com";
$emailB = "testB_{$stamp}@example.com";
$password = "TestPass123";

echo "Running automated tests against " . BASE_URL . "\n";
echo "Test accounts: $emailA / $emailB\n";

// ===================== 1. AUTH =====================
section("1. Authentication");

req('POST', '/auth/register.php', $cookieA, [
    'name' => 'Test User A',
    'email' => $emailA,
    'password' => $password,
    'confirm_password' => $password
]);
req('POST', '/auth/register.php', $cookieB, [
    'name' => 'Test User B',
    'email' => $emailB,
    'password' => $password,
    'confirm_password' => $password
]);

$stmt = mysqli_prepare($conn, "SELECT id, name FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $emailA);
mysqli_stmt_execute($stmt);
$userA = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT id, name FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $emailB);
mysqli_stmt_execute($stmt);
$userB = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

check("Register creates user A in DB", $userA !== null);
check("Register creates user B in DB", $userB !== null);

if (!$userA || !$userB) {
    echo "\nCannot continue without both test users. Check config at top of this file (BASE_URL, DB creds) and that Apache/MySQL are running.\n";
    exit(1);
}

$userAId = (int) $userA['id'];
$userBId = (int) $userB['id'];

req('POST', '/auth/login.php', $cookieA, ['email' => $emailA, 'password' => $password]);
req('POST', '/auth/login.php', $cookieB, ['email' => $emailB, 'password' => $password]);

$feedA = req('GET', '/index.php', $cookieA);
check("User A can access feed after login", strpos($feedA, 'Welcome back') !== false);

$loginWrong = req('POST', '/auth/login.php', tempnam(sys_get_temp_dir(), 'tmp_'), ['email' => $emailA, 'password' => 'wrongpass']);
check("Wrong password shows error", strpos($loginWrong, 'Incorrect email or password') !== false);


// ===================== 2. POSTS =====================
section("2. Posts");

req('POST', '/posts/create.php', $cookieA, ['content' => 'Automated test post from A']);

$stmt = mysqli_prepare($conn, "SELECT id, content FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userAId);
mysqli_stmt_execute($stmt);
$postA = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

check("Post created in DB", $postA && $postA['content'] === 'Automated test post from A');
$postAId = $postA['id'] ?? 0;

// Like as B
$likeResult = reqJson('POST', '/posts/like.php', $cookieB, ['post_id' => $postAId]);
check("User B can like A's post", ($likeResult['success'] ?? false) === true && ($likeResult['liked'] ?? false) === true);

$stmt = mysqli_prepare($conn, "SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $postAId, $userBId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Like row exists in DB", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);

// Unlike
$unlikeResult = reqJson('POST', '/posts/like.php', $cookieB, ['post_id' => $postAId]);
check("Unlike toggles off", ($unlikeResult['liked'] ?? true) === false);

// Comment as B
$commentResult = reqJson('POST', '/posts/comment.php', $cookieB, ['post_id' => $postAId, 'comment' => 'Nice post!']);
check("Comment created via AJAX", ($commentResult['success'] ?? false) === true);
$commentId = $commentResult['comment_id'] ?? 0;

// Try deleting B's comment as A (should fail — not the owner)
$deleteAsWrongUser = reqJson('POST', '/posts/comment_delete.php', $cookieA, ['comment_id' => $commentId]);
check("Cannot delete someone else's comment", ($deleteAsWrongUser['success'] ?? true) === false);

$stmt = mysqli_prepare($conn, "SELECT id FROM comments WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $commentId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Comment still exists after failed delete attempt", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);

// Delete own comment as B
$deleteOwn = reqJson('POST', '/posts/comment_delete.php', $cookieB, ['comment_id' => $commentId]);
check("Owner can delete own comment", ($deleteOwn['success'] ?? false) === true);

// Try deleting A's post as B (should fail)
req('GET', '/posts/delete.php?id=' . $postAId, $cookieB);
$stmt = mysqli_prepare($conn, "SELECT id FROM posts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $postAId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Non-owner cannot delete another user's post", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);

// Share as B
$shareResult = reqJson('POST', '/posts/share.php', $cookieB, ['post_id' => $postAId]);
check("User B can share A's post", ($shareResult['success'] ?? false) === true && ($shareResult['shared'] ?? false) === true);

$stmt = mysqli_prepare($conn, "SELECT id FROM shares WHERE post_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $postAId, $userBId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Share row exists in DB", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);


// ===================== 3. FRIENDS =====================
section("3. Friends");

req('GET', '/friends/add.php?id=' . $userBId, $cookieA);

$stmt = mysqli_prepare($conn, "SELECT status FROM friends WHERE user_id = ? AND friend_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $userAId, $userBId);
mysqli_stmt_execute($stmt);
$friendRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
check("Friend request created as pending", $friendRow && $friendRow['status'] === 'pending');

// Duplicate request should be blocked
req('GET', '/friends/add.php?id=' . $userBId, $cookieA);
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM friends WHERE user_id = ? AND friend_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $userAId, $userBId);
mysqli_stmt_execute($stmt);
$dupCount = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);
check("Duplicate friend request is blocked (still only 1 row)", (int)$dupCount === 1);

// Accept as B
req('GET', '/friends/respond.php?id=' . $userAId . '&action=accept', $cookieB);
$stmt = mysqli_prepare($conn, "SELECT status FROM friends WHERE user_id = ? AND friend_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $userAId, $userBId);
mysqli_stmt_execute($stmt);
$acceptedRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
check("Friend request accepted", $acceptedRow && $acceptedRow['status'] === 'accepted');


// ===================== 4. FOLLOW =====================
section("4. Follow");

$followResult = reqJson('POST', '/follow/toggle.php', $cookieA, ['user_id' => $userBId]);
check("A can follow B", ($followResult['success'] ?? false) === true && ($followResult['following'] ?? false) === true);

$stmt = mysqli_prepare($conn, "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $userAId, $userBId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Follow row exists in DB", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);


// ===================== 5. CHAT (requires friendship, already accepted above) =====================
section("5. Chat");

$sendResult = reqJson('POST', '/chat/send.php', $cookieA, ['friend_id' => $userBId, 'message' => 'Hello from automated test']);
check("A can send message to friend B", ($sendResult['success'] ?? false) === true);

$stmt = mysqli_prepare($conn, "SELECT id FROM messages WHERE sender_id = ? AND receiver_id = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $userAId, $userBId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Message row exists in DB", mysqli_stmt_num_rows($stmt) === 1);
mysqli_stmt_close($stmt);

$pollResult = reqJson('GET', '/chat/poll.php?friend_id=' . $userAId . '&after_id=0', $cookieB);
check("B can poll and see A's message", ($pollResult['success'] ?? false) === true && count($pollResult['messages'] ?? []) > 0);

// Remove friendship, then confirm chat is blocked
req('GET', '/friends/remove.php?id=' . $userBId, $cookieA);
$blockedSend = reqJson('POST', '/chat/send.php', $cookieA, ['friend_id' => $userBId, 'message' => 'Should fail']);
check("Chat blocked after unfriending", ($blockedSend['forbidden'] ?? false) === true);

// Re-friend for later group tests (not required, but harmless) — skipped, groups don't need friendship


// ===================== 6. GROUPS =====================
section("6. Groups");

req('POST', '/groups/create.php', $cookieA, ['name' => 'Automated Test Group ' . $stamp, 'description' => 'Created by test script']);

$stmt = mysqli_prepare($conn, "SELECT id FROM `groups` WHERE created_by = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userAId);
mysqli_stmt_execute($stmt);
$group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
check("Group created in DB", $group !== null);
$groupId = $group['id'] ?? 0;

$stmt = mysqli_prepare($conn, "SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $groupId, $userAId);
mysqli_stmt_execute($stmt);
$creatorRole = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
check("Creator auto-added as admin", $creatorRole && $creatorRole['role'] === 'admin');

// B joins
$joinResult = reqJson('POST', '/groups/join.php', $cookieB, ['group_id' => $groupId]);
check("B can join the group", ($joinResult['success'] ?? false) === true);


req('POST', '/groups/create_post.php', $cookieB, ['group_id' => $groupId, 'content' => 'Group post from B']);
$stmt = mysqli_prepare($conn, "SELECT id, content FROM posts WHERE group_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $groupId, $userBId);
mysqli_stmt_execute($stmt);
$groupPost = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
check("Member can post in group", $groupPost && $groupPost['content'] === 'Group post from B');

check("Group post correctly tagged with group_id (won't leak to main feed)", $groupPost !== null);

$wrongRemove = reqJson('POST', '/groups/remove_member.php', $cookieB, ['group_id' => $groupId, 'user_id' => $userAId]);
check("Non-admin cannot remove members", ($wrongRemove['success'] ?? true) === false);

$removeResult = reqJson('POST', '/groups/remove_member.php', $cookieA, ['group_id' => $groupId, 'user_id' => $userBId]);
check("Admin can remove a member", ($removeResult['success'] ?? false) === true);

$stmt = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $groupId, $userBId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Removed member no longer in group_members", mysqli_stmt_num_rows($stmt) === 0);
mysqli_stmt_close($stmt);

req('GET', '/groups/delete.php?id=' . $groupId, $cookieA);
$stmt = mysqli_prepare($conn, "SELECT id FROM `groups` WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $groupId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
check("Group deleted from DB", mysqli_stmt_num_rows($stmt) === 0);
mysqli_stmt_close($stmt);


section("Cleanup");

mysqli_query($conn, "DELETE FROM users WHERE id IN ($userAId, $userBId)");
echo "  Test accounts and all related data removed (posts/comments/likes/etc. cascade automatically).\n";

@unlink($cookieA);
@unlink($cookieB);


echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

if ($fail > 0) {
    echo "\nFailed checks:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
} else {
    echo "\nAll tests passed!\n";
    exit(0);
}
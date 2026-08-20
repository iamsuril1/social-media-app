<?php
$pageTitle = "Edit Profile";
$pageCss = "profile.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$errors = [];

// Fetch current data
$stmt = mysqli_prepare($conn, "SELECT name, bio FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $bio = sanitize($_POST['bio']);

    if (empty($name)) {
        $errors[] = "Name cannot be empty.";
    }

    if (strlen($name) > 100) {
        $errors[] = "Name is too long.";
    }

    if (strlen($bio) > 300) {
        $errors[] = "Bio must be under 300 characters.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, bio = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $bio, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            // Keep session name in sync with the navbar/greeting
            $_SESSION['user_name'] = $name;
            setFlash("Profile updated successfully!");
            redirect('/social-media-app/profile/view.php');
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }

    $user['name'] = $name;
    $user['bio'] = $bio;
}
?>

<div class="edit-profile-card">
    <h2>Edit Profile</h2>
    <div class="accent-bar"></div>

    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
        </div>

        <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" rows="4" placeholder="Tell people a little about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            <span class="char-hint">Max 300 characters</span>
        </div>

        <div class="edit-profile-footer">
            <a href="/social-media-app/profile/view.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-post">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = "Create Group";
$pageCss = "groups.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);

    if (empty($name)) {
        $errors[] = "Group name is required.";
    }

    if (strlen($name) > 150) {
        $errors[] = "Group name is too long.";
    }

    if (strlen($description) > 500) {
        $errors[] = "Description must be under 500 characters.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, description, created_by) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $name, $description, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            $group_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Automatically add the creator as an admin member
            $member_stmt = mysqli_prepare($conn, "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')");
            mysqli_stmt_bind_param($member_stmt, "ii", $group_id, $user_id);
            mysqli_stmt_execute($member_stmt);
            mysqli_stmt_close($member_stmt);

            setFlash("Group created successfully!");
            redirect('/social-media-app/groups/single.php?id=' . $group_id);
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<div class="group-form-card">
    <h2>Create a Group</h2>
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
            <label>Group Name</label>
            <input type="text" name="name" placeholder="e.g. Kathmandu Hikers"
                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4" placeholder="What is this group about?"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            <span class="char-hint">Max 500 characters</span>
        </div>

        <div class="group-form-footer">
            <a href="/social-media-app/groups/view.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-post">Create Group</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = "Edit Profile";
$pageCss = "profile.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$errors = [];

// Fetch current data
$stmt = mysqli_prepare($conn, "SELECT name, bio, profile_pic FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $bio = sanitize($_POST['bio']);
    $remove_pic = isset($_POST['remove_pic']);
    $profile_pic = $user['profile_pic']; // keep existing by default

    if (empty($name)) {
        $errors[] = "Name cannot be empty.";
    }

    if (strlen($name) > 100) {
        $errors[] = "Name is too long.";
    }

    if (strlen($bio) > 300) {
        $errors[] = "Bio must be under 300 characters.";
    }

    // Remove existing picture if requested
    if ($remove_pic && !empty($user['profile_pic'])) {
        $old_path = __DIR__ . '/../assets/uploads/profile/' . $user['profile_pic'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
        $profile_pic = null;
    }

    // Handle new picture upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 3 * 1024 * 1024; // 3MB

        $file_type = $_FILES['profile_pic']['type'];
        $file_size = $_FILES['profile_pic']['size'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_error = $_FILES['profile_pic']['error'];

        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "There was an error uploading the image.";
        } elseif (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF, or WEBP images are allowed.";
        } elseif ($file_size > $max_size) {
            $errors[] = "Image must be smaller than 3MB.";
        } else {
            // Delete the old picture before saving the new one
            if (!empty($user['profile_pic'])) {
                $old_path = __DIR__ . '/../assets/uploads/profile/' . $user['profile_pic'];
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }

            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_pic_name = "user_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $upload_path = __DIR__ . '/../assets/uploads/profile/' . $new_pic_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $profile_pic = $new_pic_name;
            } else {
                $errors[] = "Failed to save the uploaded image.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, bio = ?, profile_pic = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssi", $name, $bio, $profile_pic, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            $_SESSION['user_name'] = $name;
            $_SESSION['profile_pic'] = $profile_pic;
            setFlash("Profile updated successfully!");
            redirect('/social-media-app/profile/view.php');
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }

    $user['name'] = $name;
    $user['bio'] = $bio;
    $user['profile_pic'] = $profile_pic;
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

    <form method="POST" action="" enctype="multipart/form-data">

        <div class="profile-pic-upload-box">
            <div class="current-pic-preview" id="picPreviewWrapper">
                <?php echo renderAvatar($user['name'], $user['profile_pic'], 'avatar-preview-large'); ?>
            </div>
            <div class="pic-upload-controls">
                <label for="profile_pic" class="btn-upload-pic">Change Photo</label>
                <input type="file" name="profile_pic" id="profile_pic" accept="image/*" onchange="previewPic(event)">
                <?php if (!empty($user['profile_pic'])): ?>
                    <label class="remove-pic-label">
                        <input type="checkbox" name="remove_pic" value="1">
                        Remove current photo
                    </label>
                <?php endif; ?>
            </div>
        </div>

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

<script>
function previewPic(event) {
    const wrapper = document.getElementById('picPreviewWrapper');
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            wrapper.innerHTML = '<img src="' + e.target.result + '" class="avatar-img avatar-preview-large" alt="Preview">';
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
$pageTitle = "Edit Post";
$pageCss = "posts.css";
require_once __DIR__ . '/../includes/header.php';

$user_id = currentUserId();
$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = [];

if ($post_id <= 0) {
    setFlash("Invalid post.");
    redirect('/social-media-app/index.php');
}

// Fetch the post and verify ownership
$stmt = mysqli_prepare($conn, "SELECT * FROM posts WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$post) {
    setFlash("Post not found.");
    redirect('/social-media-app/index.php');
}

if ($post['user_id'] != $user_id) {
    setFlash("You don't have permission to edit this post.");
    redirect('/social-media-app/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $content = sanitize($_POST['content']);
    $remove_image = isset($_POST['remove_image']) ? true : false;
    $image_name = $post['image']; // keep existing image by default

    if (empty($content) && empty($_FILES['image']['name']) && (empty($image_name) || $remove_image)) {
        $errors[] = "Post must have text or an image.";
    }

    if (strlen($content) > 2000) {
        $errors[] = "Post is too long (max 2000 characters).";
    }

    // Remove existing image if requested
    if ($remove_image && !empty($post['image'])) {
        $old_path = __DIR__ . '/../assets/uploads/posts/' . $post['image'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
        $image_name = null;
    }

    // Handle new image upload (replaces existing one)
    if (!empty($_FILES['image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_error = $_FILES['image']['error'];

        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "There was an error uploading the image.";
        } elseif (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF, or WEBP images are allowed.";
        } elseif ($file_size > $max_size) {
            $errors[] = "Image must be smaller than 5MB.";
        } else {
            // Delete the old image before saving the new one
            if (!empty($post['image'])) {
                $old_path = __DIR__ . '/../assets/uploads/posts/' . $post['image'];
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }

            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_image_name = "post_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $upload_path = __DIR__ . '/../assets/uploads/posts/' . $new_image_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $image_name = $new_image_name;
            } else {
                $errors[] = "Failed to save the uploaded image.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE posts SET content = ?, image = ? WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ssii", $content, $image_name, $post_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            setFlash("Post updated successfully!");
            redirect('/social-media-app/index.php');
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }

    // Refresh $post data in memory so the form re-renders correctly on error
    $post['content'] = $_POST['content'];
    $post['image'] = $image_name;
}
?>

<div class="create-post-card">
    <h2>Edit Post</h2>
    <div class="accent-bar"></div>

    <?php if (!empty($errors)): ?>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <textarea name="content" rows="5"><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>

        <?php if (!empty($post['image'])): ?>
            <div class="current-image-box">
                <img src="/social-media-app/assets/uploads/posts/<?php echo htmlspecialchars($post['image']); ?>" alt="Current image" class="current-image">
                <label class="remove-image-label">
                    <input type="checkbox" name="remove_image" value="1">
                    Remove this image
                </label>
            </div>
        <?php endif; ?>

        <div class="image-upload-box">
            <label for="image" class="image-upload-label">
                <span class="upload-icon">📷</span>
                <span id="uploadText"><?php echo !empty($post['image']) ? "Replace photo" : "Add a photo (optional)"; ?></span>
            </label>
            <input type="file" name="image" id="image" accept="image/*" onchange="previewImage(event)">
            <img id="imagePreview" class="image-preview" style="display:none;">
        </div>

        <div class="create-post-footer">
            <a href="/social-media-app/index.php" class="btn-cancel">Cancel</a>
            <button type="submit" class="btn-post">Save Changes</button>
        </div>
    </form>
</div>

<script>
function previewImage(event) {
    const preview = document.getElementById('imagePreview');
    const uploadText = document.getElementById('uploadText');
    const file = event.target.files[0];

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        uploadText.textContent = file.name;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
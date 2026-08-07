<?php
$pageTitle = "Create Post";
$pageCss = "posts.css";
require_once __DIR__ . '/../includes/header.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $content = sanitize($_POST['content']);
    $user_id = currentUserId();
    $image_name = null;

    if (empty($content) && empty($_FILES['image']['name'])) {
        $errors[] = "Post must have text or an image.";
    }

    if (strlen($content) > 2000) {
        $errors[] = "Post is too long (max 2000 characters).";
    }

    // Handle image upload if one was provided
    if (!empty($_FILES['image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

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
            // Build a safe, unique filename
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = "post_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $upload_path = __DIR__ . '/../assets/uploads/posts/' . $image_name;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $errors[] = "Failed to save the uploaded image.";
                $image_name = null;
            }
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $content, $image_name);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            setFlash("Post created successfully!");
            redirect('/social-media-app/index.php');
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<div class="create-post-card">
    <h2>Create a Post</h2>
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
            <textarea name="content" placeholder="What's on your mind, <?php echo htmlspecialchars($_SESSION['user_name']); ?>?" rows="5"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
        </div>

        <div class="image-upload-box">
            <label for="image" class="image-upload-label">
                <span class="upload-icon">📷</span>
                <span id="uploadText">Add a photo (optional)</span>
            </label>
            <input type="file" name="image" id="image" accept="image/*" onchange="previewImage(event)">
            <img id="imagePreview" class="image-preview" style="display:none;">
        </div>

        <div class="create-post-footer">
            <span class="char-hint">Max 2000 characters · Max image size 5MB</span>
            <button type="submit" class="btn-post">Post</button>
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
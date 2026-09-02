<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user_id = currentUserId();
$group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;

if ($group_id <= 0) {
    setFlash("Invalid group.");
    redirect('/social-media-app/groups/view.php');
}

$member_check = mysqli_prepare($conn, "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
mysqli_stmt_bind_param($member_check, "ii", $group_id, $user_id);
mysqli_stmt_execute($member_check);
mysqli_stmt_store_result($member_check);

if (mysqli_stmt_num_rows($member_check) === 0) {
    mysqli_stmt_close($member_check);
    setFlash("You must be a member of this group to post.");
    redirect('/social-media-app/groups/single.php?id=' . $group_id);
}
mysqli_stmt_close($member_check);

$content = sanitize($_POST['content']);
$image_name = null;

if (empty($content) && empty($_FILES['image']['name'])) {
    setFlash("Post must have text or an image.");
    redirect('/social-media-app/groups/single.php?id=' . $group_id);
}

if (strlen($content) > 2000) {
    setFlash("Post is too long (max 2000 characters).");
    redirect('/social-media-app/groups/single.php?id=' . $group_id);
}

if (!empty($_FILES['image']['name'])) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 20 * 1024 * 1024;

    $file_type = $_FILES['image']['type'];
    $file_size = $_FILES['image']['size'];
    $file_tmp = $_FILES['image']['tmp_name'];
    $file_error = $_FILES['image']['error'];

    if ($file_error !== UPLOAD_ERR_OK) {
        setFlash("There was an error uploading the image.");
        redirect('/social-media-app/groups/single.php?id=' . $group_id);
    } elseif (!in_array($file_type, $allowed_types)) {
        setFlash("Only JPG, PNG, GIF, or WEBP images are allowed.");
        redirect('/social-media-app/groups/single.php?id=' . $group_id);
    } elseif ($file_size > $max_size) {
        setFlash("Image must be smaller than 5MB.");
        redirect('/social-media-app/groups/single.php?id=' . $group_id);
    } else {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = "post_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
        $upload_path = __DIR__ . '/../assets/uploads/posts/' . $image_name;

        if (!move_uploaded_file($file_tmp, $upload_path)) {
            setFlash("Failed to save the uploaded image.");
            redirect('/social-media-app/groups/single.php?id=' . $group_id);
        }
    }
}

$stmt = mysqli_prepare($conn, "INSERT INTO posts (user_id, group_id, content, image) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iiss", $user_id, $group_id, $content, $image_name);

if (mysqli_stmt_execute($stmt)) {
    setFlash("Posted to the group!");
} else {
    setFlash("Something went wrong. Please try again.");
}
mysqli_stmt_close($stmt);

redirect('/social-media-app/groups/single.php?id=' . $group_id);
?>
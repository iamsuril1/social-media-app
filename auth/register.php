<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $errors[] = "An account with this email already exists.";
        }
        mysqli_stmt_close($check);
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hashed_password);

        if (mysqli_stmt_execute($stmt)) {
            setFlash("Account created successfully! Please log in.");
            redirect('/social-media-app/auth/login.php');
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Social Media App</title>
    <link rel="stylesheet" href="/social-media-app/assets/css/auth.css">
</head>
<body class="auth-page">

    <span class="auth-dot"></span>

    <div class="auth-split">

        <!-- Left decorative panel -->
        <div class="auth-illustration">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>

            <div class="auth-brand">
                <span class="brand-icon">S</span>
                <span>SocialApp</span>
            </div>

            <div class="auth-illustration-text">
                <h3>Connect with everyone that matters</h3>
                <p>Share posts, chat with friends, and stay close to the people and groups you care about.</p>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="auth-form-panel">
            <h2>Registration</h2>
            <div class="auth-accent-bar"></div>

            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Please enter your name"
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Please enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">Show</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">Show</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Register <span class="arrow">→</span>
                </button>
            </form>

            <p class="auth-footer">Already have an account? <a href="login.php">Sign in</a></p>
        </div>

    </div>

    <script>
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
                btn.textContent = "Hide";
            } else {
                field.type = "password";
                btn.textContent = "Show";
            }
        }
    </script>

</body>
</html>
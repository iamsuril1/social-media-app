<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $errors[] = "Please enter both email and password.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id, name, password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            redirect('/social-media-app/index.php');
        } else {
            $errors[] = "Incorrect email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Social Media App</title>
    <link rel="stylesheet" href="/social-media-app/assets/css/login.css">
</head>
<body class="login-page">

    <span class="login-dot"></span>

    <div class="login-split">

        <!-- Left decorative panel -->
        <div class="login-illustration">
            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
            <div class="blob blob-3"></div>

            <div class="login-brand">
                <span class="brand-icon">S</span>
                <span>SocialApp</span>
            </div>

            <div class="login-illustration-text">
                <h3>Welcome back</h3>
                <p>Log in to catch up with your friends, groups, and everything you missed.</p>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="login-form-panel">
            <h2>Login</h2>
            <div class="login-accent-bar"></div>

            <?php if ($flash): ?>
                <div class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">Show</button>
                    </div>
                </div>

                <div class="login-options">
                    <label>
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">
                    Login <span class="arrow">→</span>
                </button>
            </form>

            <p class="login-footer">Don't have an account? <a href="register.php">Register here</a></p>
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
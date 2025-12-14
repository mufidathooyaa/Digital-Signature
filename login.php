<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (login($username, $password, $conn)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Digital Signature PKI</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>

<div class="auth-wrapper">
    <!-- LEFT SIDE -->
    <div class="auth-left">
        <img src="assets/img/rafiki.png" alt="Login Illustration">
    </div>

    <!-- RIGHT SIDE -->
    <div class="auth-right">
        <div class="login-card">
            <h2>Hello!</h2>
            <p class="subtitle">Silakan Log-In Signers</p>

            <?php if ($error): ?>
                <div class="error-box"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <span class="icon">✉</span>
                    <input type="text" name="username" placeholder="Username" required>
                </div>

                <div class="input-group">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

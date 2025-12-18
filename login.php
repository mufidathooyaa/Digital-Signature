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
    $captcha_input = $_POST['captcha']; // Ambil input captcha user
    
    // VALIDASI CAPTCHA
    if (!isset($_SESSION['captcha_code']) || $captcha_input !== $_SESSION['captcha_code']) {
        $error = 'Kode CAPTCHA salah! Silakan coba lagi.';
    } else {
        // Jika Captcha benar, lanjut cek login
        if (login($username, $password, $conn)) {
            // Hapus session captcha setelah berhasil login agar bersih
            unset($_SESSION['captcha_code']); 
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
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

                <div class="captcha-wrapper" style="margin-bottom: 15px; text-align: center;">
                    <img src="includes/captcha.php" alt="CAPTCHA" style="border-radius: 8px; border: 1px solid #ddd; margin-bottom: 10px;">
                    <div class="input-group">
                        <span class="icon">🛡️</span>
                        <input type="text" name="captcha" placeholder="Ketik kode di atas" required autocomplete="off">
                    </div>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

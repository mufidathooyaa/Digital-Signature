<?php
// ganti_password.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Pastikan user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Proteksi: Jika Direksi mencoba akses, lempar ke Kelola User
if ($_SESSION['user']['role'] === 'direksi') {
    header("Location: kelola_user.php");
    exit;
}

$message = '';
$msg_type = '';

// ... (Sisa kode logic ganti password karyawan tetap sama seperti sebelumnya) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $user_id = $_SESSION['user']['id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!password_verify($old_pass, $user['password'])) {
        $msg_type = 'danger';
        $message = "Password lama salah!";
    } elseif (strlen($new_pass) < 4) {
        $msg_type = 'danger';
        $message = "Password baru minimal 4 karakter!";
    } elseif ($new_pass !== $confirm_pass) {
        $msg_type = 'danger';
        $message = "Konfirmasi password baru tidak cocok!";
    } else {
        $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt_upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_upd->bind_param("si", $new_hashed, $user_id);
        
        if ($stmt_upd->execute()) {
            $msg_type = 'success';
            $message = "Password berhasil diubah!";
        } else {
            $msg_type = 'danger';
            $message = "Terjadi kesalahan sistem.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        .form-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 40px auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .btn-full { width: 100%; padding: 12px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <div class="form-box">
            <h2 style="text-align:center;">🔒 Ganti Password</h2>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group"><label>Password Lama</label><input type="password" name="old_password" required></div>
                <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" required></div>
                <div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="confirm_password" required></div>
                <button type="submit" class="btn btn-primary btn-full">Simpan</button>
            </form>
        </div>
    </div>
</body>
</html>
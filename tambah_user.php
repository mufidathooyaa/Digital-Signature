<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Hanya Direksi yang boleh mengakses halaman ini
requireRole('direksi');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = sanitizeInput($_POST['nama_lengkap']);
    $email = sanitizeInput($_POST['email']);
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);

    // Validasi Input
    if (empty($nama_lengkap) || empty($email) || empty($username) || empty($password) || empty($role)) {
        $error = "Semua field wajib diisi!";
    } else {
        // Cek apakah username atau email sudah ada
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "Username atau Email sudah digunakan!";
        } else {
            // Hash Password (Wajib untuk keamanan)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert ke database
            // Catatan: Kolom password di database Anda sepertinya bernama 'PASSWORD' (huruf besar) berdasarkan file auth.php
            $stmt = $conn->prepare("INSERT INTO users (username, PASSWORD, email, nama_lengkap, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed_password, $email, $nama_lengkap, $role);

            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user']['id'], 'create_user', "Membuat akun baru: $username ($role)");
                $success = "Akun user berhasil dibuat!";
            } else {
                $error = "Gagal membuat akun: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>👤 Tambah Akun Karyawan</h1>
            <p>Buat akun baru untuk pegawai</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" required placeholder="Contoh: Budi Santoso">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="Contoh: budi@kantor.com">
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Username untuk login">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Password">
                </div>

                <div class="form-group">
                    <label for="role">Role / Jabatan</label>
                    <select name="role" id="role" required>
                        <option value="karyawan">Karyawan</option>
                        <option value="direksi">Direksi</option>
                        </select>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Simpan Akun</button>
                    <a href="dashboard.php" class="btn" style="background: #6b7280; color: white;">Kembali</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
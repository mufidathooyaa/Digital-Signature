<?php
// kelola_user.php - All-in-One User Management
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Proteksi: Hanya Direksi
requireRole('direksi');

$message = '';
$msg_type = '';

// --- LOGIC PENANGANAN AKSI (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Logic Tambah User
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $nama = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $pass = $_POST['password'];
        $role = sanitizeInput($_POST['role']);

        // Cek duplikat
        $cek = $conn->query("SELECT id FROM users WHERE username = '$username' OR email = '$email'");
        if ($cek->num_rows > 0) {
            $msg_type = 'danger';
            $message = "Gagal: Username atau Email sudah digunakan!";
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, PASSWORD, email, nama_lengkap, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed, $email, $nama, $role);
            
            if ($stmt->execute()) {
                $msg_type = 'success';
                $message = "User baru <strong>$nama</strong> berhasil ditambahkan!";
            } else {
                $msg_type = 'danger';
                $message = "Error: " . $conn->error;
            }
        }
    }

    // 2. Logic Reset Password
    elseif (isset($_POST['action']) && $_POST['action'] === 'reset_pass') {
        $uid = intval($_POST['user_id']);
        $new_pass = $_POST['new_password'];
        
        if (strlen($new_pass) < 4) {
            $msg_type = 'danger';
            $message = "Password minimal 4 karakter!";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET PASSWORD = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $uid);
            
            if ($stmt->execute()) {
                $msg_type = 'success';
                $message = "Password berhasil direset!";
            } else {
                $msg_type = 'danger';
                $message = "Gagal update: " . $conn->error;
            }
        }
    }

    // 3. Logic Hapus User
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $uid = intval($_POST['user_id']);
        
        // Mencegah hapus akun sendiri
        if ($uid == $_SESSION['user']['id']) {
            $msg_type = 'danger';
            $message = "Anda tidak bisa menghapus akun Anda sendiri saat sedang login!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            
            if ($stmt->execute()) {
                $msg_type = 'success';
                $message = "User berhasil dihapus permanen.";
            } else {
                // Biasanya gagal jika user sudah punya data relasi (foreign key)
                $msg_type = 'danger';
                $message = "Gagal menghapus (User mungkin memiliki data dokumen terkait): " . $conn->error;
            }
        }
    }
}

// Ambil Data User Terbaru
$users = $conn->query("SELECT * FROM users ORDER BY role ASC, created_at DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        /* Styling sederhana untuk Modal & Tabel */
        .action-bar { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Table Styles */
        .table-wrapper { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        
        /* Badges */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; }
        .badge.direksi { background: #e0e7ff; color: #3730a3; }
        .badge.karyawan { background: #d1fae5; color: #065f46; }

        /* Forms Inline */
        .reset-form { display: flex; gap: 5px; }
        .input-mini { padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 120px; font-size: 0.9rem; }
        
        /* Buttons */
        .btn-mini { padding: 6px 12px; font-size: 0.8rem; border: none; cursor: pointer; border-radius: 4px; color: white; text-decoration: none; }
        .btn-blue { background-color: #3b82f6; } .btn-blue:hover { background-color: #2563eb; }
        .btn-red { background-color: #ef4444; } .btn-red:hover { background-color: #dc2626; }
        .btn-green { background-color: #10b981; } .btn-green:hover { background-color: #059669; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 25px; border-radius: 8px; width: 500px; max-width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #666; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="action-bar">
            <div>
                <h1>👥 Manajemen User</h1>
                <p>Tambah, edit password, atau hapus user</p>
            </div>
            <button onclick="openModal()" class="btn btn-primary" style="padding: 10px 20px;">+ Tambah User Baru</button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="25%">User Info</th>
                        <th width="15%">Role</th>
                        <th width="35%">Reset Password</th>
                        <th width="10%">Hapus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; while($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(html_entity_decode($u['nama_lengkap'], ENT_QUOTES)); ?></strong><br>
                            <span style="font-size:0.85rem; color:#666;">
                                @<?php echo htmlspecialchars($u['username']); ?>
                            </span><br>
                            <span style="font-size:0.85rem; color:#888;"><?php echo htmlspecialchars($u['email']); ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['role']; ?>">
                                <?php echo ucfirst($u['role']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="reset-form" onsubmit="return confirm('Reset password untuk <?php echo $u['username']; ?>?')">
                                <input type="hidden" name="action" value="reset_pass">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="text" name="new_password" class="input-mini" placeholder="Password Baru" required>
                                <button type="submit" class="btn-mini btn-blue">Reset</button>
                            </form>
                        </td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user']['id']): ?>
                                <form method="POST" onsubmit="return confirm('PERINGATAN: Yakin hapus user <?php echo $u['nama_lengkap']; ?>? Data tidak bisa dikembalikan!')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-mini btn-red">Hapus</button>
                                </form>
                            <?php else: ?>
                                <small class="text-muted">Akun Anda</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Tambah User Baru</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" required placeholder="Ex: Budi Santoso">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="Ex: budi@kantor.com">
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Username Login">
                </div>

                <div class="form-group">
                    <label>Password Awal</label>
                    <input type="password" name="password" required placeholder="Minimal 4 karakter">
                </div>

                <div class="form-group">
                    <label>Role / Jabatan</label>
                    <select name="role" required>
                        <option value="karyawan">Karyawan</option>
                        <option value="direksi">Direksi</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="width:100%; margin-top:10px;">Simpan User</button>
            </form>
        </div>
    </div>

    <script>
        // Script Sederhana untuk Modal
        const modal = document.getElementById("modalTambah");
        
        function openModal() { modal.style.display = "block"; }
        function closeModal() { modal.style.display = "none"; }
        
        // Tutup jika klik di luar modal
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
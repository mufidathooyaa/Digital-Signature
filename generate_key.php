<?php
/**
 * Generate Key Page (SECURE VERSION)
 * Meminta Passphrase agar Private Key terenkripsi di Database
 */

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/crypto.php';
require_once 'includes/functions.php';

requireRole('direksi');

$success = '';
$error = '';
$keypair = null;
$existingKey = getActiveKeyPair($conn, $_SESSION['user']['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $passphrase = $_POST['passphrase'];
    $passphrase_confirm = $_POST['passphrase_confirm'];

    // Validasi Password
    if (empty($passphrase) || strlen($passphrase) < 6) {
        $error = "Passphrase harus diisi minimal 6 karakter!";
    } elseif ($passphrase !== $passphrase_confirm) {
        $error = "Konfirmasi passphrase tidak cocok!";
    } else {
        // Generate new keypair DENGAN Passphrase
        $newKeypair = generateKeyPair($passphrase);
        
        if ($newKeypair === false) {
            $error = "Gagal generate kunci. Error: " . openssl_error_string();
        } else {
            if (saveKeyPair($conn, $_SESSION['user']['id'], $newKeypair['public_key'], $newKeypair['private_key'])) {
                logActivity($conn, $_SESSION['user']['id'], 'generate_keypair', 'Generate pasangan kunci RSA baru (Encrypted)');
                $success = "Kunci berhasil dibuat dan diamankan dengan Passphrase!";
                $existingKey = $newKeypair;
            } else {
                $error = "Gagal menyimpan kunci ke database.";
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
    <title>Generate Key - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔑 Manajemen Kunci Keamanan</h1>
            <p>Buat Passphrase untuk mengamankan tanda tangan digital Anda</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2>Status Kunci Saat Ini</h2>
            
            <?php if ($existingKey): ?>
                <div class="alert alert-success">
                    ✅ <strong>Anda sudah memiliki kunci aktif.</strong><br>
                    Private Key Anda tersimpan aman (terenkripsi) di database. Anda memerlukan Passphrase untuk menggunakannya.
                </div>
                
                <div style="margin-top: 15px; padding: 15px; background: #f3f4f6; border-radius: 8px;">
                    <strong>Public Key (Boleh dibagikan):</strong>
                    <textarea class="form-control" readonly style="height: 100px; margin-top: 5px; font-size: 11px;"><?php echo htmlspecialchars($existingKey['public_key']); ?></textarea>
                </div>

                <div class="alert alert-info" style="margin-top: 20px;">
                    🛡️ <strong>Private Key tidak ditampilkan</strong> demi keamanan. Key tersebut tersimpan di server dan hanya bisa dibuka dengan Passphrase Anda saat penandatanganan.
                </div>
                
            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ Anda belum memiliki kunci. Silakan buat kunci baru di bawah ini.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-container" style="margin-top: 30px; border-top: 4px solid #3b82f6;">
            <h2>Generate Kunci & Passphrase Baru</h2>
            
            <?php if ($existingKey): ?>
                <div class="alert alert-warning">
                    ⚠️ <strong>Perhatian:</strong> Membuat kunci baru akan menonaktifkan kunci lama. Pastikan Anda mengingat Passphrase baru Anda, karena <strong>tidak bisa di-reset</strong>.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin membuat pasangan kunci baru?')">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: bold;">Buat Passphrase (Password Kunci)</label>
                    <input type="password" name="passphrase" class="form-control" required minlength="6" placeholder="Minimal 6 karakter..." style="width: 100%; padding: 10px; margin-top: 5px;">
                    <small style="color: #666;">Password ini akan diminta setiap kali Anda menandatangani dokumen.</small>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: bold;">Konfirmasi Passphrase</label>
                    <input type="password" name="passphrase_confirm" class="form-control" required minlength="6" placeholder="Ketik ulang passphrase..." style="width: 100%; padding: 10px; margin-top: 5px;">
                </div>

                <button type="submit" name="generate" class="btn btn-primary" style="padding: 12px 25px;">
                    🔄 Generate Kunci & Amankan
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireRole('karyawan');

$success = '';
$error = '';

$user_id = $_SESSION['user']['id'];
$user = getUserById($conn, $user_id);
$nama_pengaju = $user['nama_lengkap'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis_dokumen = sanitizeInput($_POST['jenis_dokumen']);
    
    // 1. OTOMATISASI DATA
    // Generate Nomor Surat Baru oleh Sistem
    $nomor_surat = generateNomorSurat($conn, $jenis_dokumen);
    
    // Set tanggal hari ini
    $tanggal_mulai = date('Y-m-d');
    $tanggal_selesai = date('Y-m-d');
    
    // Buat keterangan standar
    $keterangan = "Pengajuan dokumen " . strtoupper(str_replace('_', ' ', $jenis_dokumen)) . " oleh " . $nama_pengaju;

    // 2. VALIDASI & UPLOAD
    if (!isset($_FILES['file_pendukung']) || $_FILES['file_pendukung']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Anda wajib mengupload file dokumen yang sudah diisi!";
    } else {
        // Proses Upload File
        $file_path = null;
        if ($_FILES['file_pendukung']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadFile($_FILES['file_pendukung']);
            if ($upload_result['success']) {
                $file_path = $upload_result['file_path'];
            } else {
                $error = $upload_result['message'];
            }
        }
        
        // 3. SIMPAN KE DATABASE
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO documents (user_id, nomor_surat, jenis_dokumen, nama_pengaju, jabatan_pengaju, tanggal_mulai, tanggal_selesai, keterangan, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $jabatan = 'Karyawan'; 
            $stmt->bind_param("issssssss", $user_id, $nomor_surat, $jenis_dokumen, $nama_pengaju, $jabatan, $tanggal_mulai, $tanggal_selesai, $keterangan, $file_path);
            
            if ($stmt->execute()) {
                logActivity($conn, $_SESSION['user']['id'], 'create_document', "Membuat pengajuan $jenis_dokumen ($nomor_surat)");
                $success = "Pengajuan berhasil! Nomor Surat Sistem: <strong>$nomor_surat</strong>";
            } else {
                $error = "Gagal menyimpan data. Silakan coba lagi.";
                // Hapus file jika database gagal
                if ($file_path && file_exists($file_path)) unlink($file_path);
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
    <title>Upload Dokumen - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📤 Upload Pengajuan Dokumen</h1>
            <p>Silakan upload template dokumen yang telah Anda download dan isi.</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="jenis_dokumen">Jenis Dokumen *</label>
                    <select name="jenis_dokumen" id="jenis_dokumen" required>
                        <option value="">-- Pilih Jenis Template --</option>
                        <option value="dana">Formulir Pengajuan Dana</option>
                        <option value="tugas">Surat Tugas</option>
                        <option value="bast">Berita Acara (BAST)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="file_pendukung">Upload File PDF *</label>
                    <input type="file" name="file_pendukung" id="file_pendukung" accept=".pdf" required>
                    <small style="color: #666;">Upload file PDF yang sudah diisi. Nomor surat akan dibuatkan otomatis oleh sistem.</small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Upload & Ajukan</button>
                    <a href="dashboard.php" class="btn" style="background: #ff0000ff; color: white;">Batal</a>
                </div>
            </form>
        </div>
        
        <div class="form-container" style="margin-top: 20px; background: #dbeafe;">
            <h3 style="color: #1e40af; margin-bottom: 10px;">ℹ️ Alur Pengajuan</h3>
            <ul style="color: #1e40af; padding-left: 20px;">
                <li>Download template di Dashboard.</li>
                <li>Isi data yang diperlukan (biarkan bagian Nomor Surat kosong/sesuai template).</li>
                <li>Simpan/Export ke <strong>PDF</strong>.</li>
                <li>Upload di sini. Sistem akan otomatis memberikan <strong>Nomor Surat Resmi</strong> saat data tersimpan.</li>
            </ul>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
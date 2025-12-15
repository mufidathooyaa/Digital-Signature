<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

// Get statistik
$user_id = $_SESSION['user']['id'];
$role     = $_SESSION['user']['role'];

// Statistik berdasarkan role
if ($role === 'karyawan') {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM documents");
}

if ($role === 'karyawan') {
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} else {
    $stats = $stmt->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Selamat datang, <strong><?php echo $_SESSION['user']['nama']; ?></strong> (<?php echo ucfirst($role); ?>)</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Dokumen</p>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Menunggu</p>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Disetujui</p>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">❌</div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Ditolak</p>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>Menu Cepat</h2>
            <div class="action-grid">
                <?php if ($role === 'karyawan'): ?>
                    <a href="pengajuan.php" class="action-card">
                        <div class="action-icon">➕</div>
                        <h3>Buat Pengajuan</h3>
                        <p>Ajukan Izin Sakit atau Kegiatan Dinas</p>
                    </a>
                <?php endif; ?>
                
                <?php if ($role === 'direksi'): ?>
                    <a href="kelola_pengajuan.php" class="action-card">
                        <div class="action-icon">📋</div>
                        <h3>Kelola Pengajuan</h3>
                        <p>Review dan proses dokumen</p>
                    </a>
                <?php endif; ?>
                
                <?php if ($role === 'direksi'): ?>
                    <a href="generate_key.php" class="action-card">
                        <div class="action-icon">🔑</div>
                        <h3>Generate Key</h3>
                        <p>Buat pasangan kunci RSA</p>
                    </a>
                    
                    <a href="tanda_tangan.php" class="action-card">
                        <div class="action-icon">✍️</div>
                        <h3>Tanda Tangan</h3>
                        <p>Tandatangani dokumen digital</p>
                    </a>
                <?php endif; ?>
                
                <?php if ($role === 'direksi'): ?>
                    <a href="kelola_user.php" class="action-card">
                        <div class="action-icon">👤</div>
                        <h3>Kelola User</h3>
                        <p>Kelola Akun User</p>
                    </a>
                <?php endif; ?>

                <a href="verifikasi.php" class="action-card">
                    <div class="action-icon">🔍</div>
                    <h3>Verifikasi</h3>
                    <p>Cek keaslian dokumen</p>
                </a>
                
                <a href="riwayat.php" class="action-card">
                    <div class="action-icon">📊</div>
                    <h3>Riwayat</h3>
                    <p>Lihat log aktivitas</p>
                </a>
            </div>
        </div>

        <?php if ($role === 'karyawan'): ?>
        <div class="quick-actions" style="margin-top: 30px;">
            <h2>📂 Download Template Dokumen</h2>
            <p style="color: #666; margin-bottom: 15px;">Silakan unduh template di bawah ini, isi datanya, lalu upload kembali di menu "Buat Pengajuan".</p>
            
            <div class="action-grid">
                <a href="download.php?source=template&file=Template_Pengajuan_Dana.docx" class="action-card">
                    <div class="action-icon" style="font-size: 40px;">💰</div>
                    <h3>Pengajuan Dana</h3>
                    <p>Format: Microsoft Word (.docx)</p>
                </a>

                <a href="download.php?source=template&file=Template_Surat_Tugas.docx" class="action-card">
                    <div class="action-icon" style="font-size: 40px;">📋</div>
                    <h3>Surat Tugas</h3>
                    <p>Format: Microsoft Word (.docx)</p>
                </a>

                <a href="download.php?source=template&file=Template_BAST.docx" class="action-card">
                    <div class="action-icon" style="font-size: 40px;">🤝</div>
                    <h3>Berita Acara (BAST)</h3>
                    <p>Format: Microsoft Word (.docx)</p>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <script src="assets/js/script.js"></script>
</body>
</html>
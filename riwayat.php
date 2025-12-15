<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// Get activity logs
if ($role === 'karyawan') {
    $stmt = $conn->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query("SELECT a.*, u.nama_lengkap, u.role 
                         FROM activity_logs a 
                         JOIN users u ON a.user_id = u.id 
                         ORDER BY a.created_at DESC 
                         LIMIT 100");
}

// Get documents
// PERBAIKAN: Pastikan memilih file_path juga
if ($role === 'karyawan') {
    $stmt = $conn->prepare("SELECT *, status AS doc_status FROM documents WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $documents = $stmt->get_result();
} else {
    $documents = $conn->query("SELECT d.*, d.status AS doc_status, u.nama_lengkap as pengaju_nama 
                              FROM documents d 
                              JOIN users u ON d.user_id = u.id 
                              ORDER BY d.created_at DESC 
                              LIMIT 50");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📊 Riwayat & Log Aktivitas</h1>
            <p>Pantau semua aktivitas sistem</p>
        </div>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Riwayat Dokumen</h2>
            
            <?php if ($documents->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No. Surat</th>
                            <th>Jenis</th>
                            <?php if ($role !== 'karyawan'): ?>
                                <th>Pengaju</th>
                            <?php endif; ?>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $documents->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $row['nomor_surat']; ?></strong></td>
                                <td><?php echo getJenisDokumen($row['jenis_dokumen']); ?></td>
                                <?php if ($role !== 'karyawan'): ?>
                                    <td><?php echo $row['pengaju_nama']; ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($row['tanggal_mulai'])); ?><br>
                                    s/d<br>
                                    <?php echo date('d/m/Y', strtotime($row['tanggal_selesai'])); ?>
                                </td>
                                <td><?php echo getStatusBadge($row['doc_status']); ?></td>
                                <td>
                                    <form method="POST" action="verifikasi.php" style="display: inline; margin-right: 5px;">
                                        <input type="hidden" name="nomor_surat" value="<?php echo $row['nomor_surat']; ?>">
                                        <button type="submit" name="verify" class="btn btn-sm" style="background: #3b82f6; color: white;">🔍 Cek</button>
                                    </form>

                                    <?php 
                                        // Cek apakah file yang sudah ditandatangani ada di folder signed_docs/
                                        $filename = basename($row['file_path']);
                                        $signedPath = "signed_docs/" . $filename;
                                        
                                        // Hanya munculkan tombol download jika:
                                        // 1. Status Approved
                                        // 2. File di folder signed_docs BENAR-BENAR ADA
                                        if ($row['doc_status'] === 'approved' && file_exists($signedPath)): 
                                    ?>
                                        <a href="download.php?source=signed&file=<?php echo basename($signedPath); ?>" target="_blank" class="btn btn-success btn-sm" style="text-decoration: none;">
                                            📥 Download PDF Sah
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Belum ada riwayat dokumen.</div>
            <?php endif; ?>
        </div>
        
        <div class="table-container" style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">Log Aktivitas Sistem</h2>
            
            <?php if ($logs->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <?php if ($role !== 'karyawan'): ?>
                                <th>User</th>
                                <th>Role</th>
                            <?php endif; ?>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <?php if ($role !== 'karyawan'): ?>
                                    <td><?php echo $row['nama_lengkap']; ?></td>
                                    <td><span class="badge badge-warning"><?php echo ucfirst($row['role']); ?></span></td>
                                <?php endif; ?>
                                <td><strong><?php echo str_replace('_', ' ', ucwords($row['ACTION'] ?? 'Unknown Action', '_')); ?></strong></td>
                                <td><?php echo $row['description']; ?></td>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Belum ada log aktivitas.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
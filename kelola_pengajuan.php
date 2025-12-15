<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireRole('direksi');

$success = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $doc_id = (int)$_POST['document_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE documents SET status = 'approved', approved_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user']['id'], $doc_id);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user']['id'], 'approve_document', "Menyetujui dokumen ID: $doc_id");
            $success = "Dokumen berhasil disetujui!";
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE documents SET status = 'rejected', approved_by = ? WHERE id = ?");
        $stmt->bind_param("ii", $_SESSION['user']['id'], $doc_id);
        
        if ($stmt->execute()) {
            logActivity($conn, $_SESSION['user']['id'], 'reject_document', "Menolak dokumen ID: $doc_id");
            $success = "Dokumen berhasil ditolak!";
        }
    }
}

// Get pending documents
$query = "SELECT d.*, u.nama_lengkap as pengaju_nama, u.email 
          FROM documents d 
          JOIN users u ON d.user_id = u.id 
          WHERE d.status = 'pending' 
          ORDER BY d.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengajuan - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>📋 Kelola Pengajuan</h1>
            <p>Review dan proses dokumen yang masuk</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="table-container">
            <h2 style="margin-bottom: 20px;">Dokumen Menunggu Persetujuan</h2>
            
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No. Surat</th>
                            <th>Jenis</th>
                            <th>Pengaju</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>File</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo $row['nomor_surat']; ?></strong></td>
                                <td><?php echo getJenisDokumen($row['jenis_dokumen']); ?></td>
                                <td>
                                    <?php echo $row['pengaju_nama']; ?><br>
                                    <small style="color: #666;"><?php echo $row['email']; ?></small>
                                </td>
                                <td>
                                    <?php echo formatTanggalIndo($row['tanggal_mulai']); ?><br>
                                    s/d<br>
                                    <?php echo formatTanggalIndo($row['tanggal_selesai']); ?>
                                </td>
                                <td><?php echo substr($row['keterangan'], 0, 50) . '...'; ?></td>
                                <td>
                                    <?php if ($row['file_path']): ?>
                                        <?php 
                                        // Ambil nama filenya saja
                                        $nama_file = basename($row['file_path']);
                                        ?>
                                        <a href="download.php?source=uploads&file=<?php echo $nama_file; ?>" target="_blank" class="btn btn-sm" style="background: #3b82f6; color: white;">Lihat</a>
                                    <?php else: ?>
                                        <em>Tidak ada</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="document_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" onclick="return confirm('Setujui dokumen ini?')">✓ Setujui</button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Tolak dokumen ini?')">✗ Tolak</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Tidak ada dokumen yang menunggu persetujuan.</div>
            <?php endif; ?>
        </div>
        
        <!-- Dokumen yang sudah diproses -->
        <div class="table-container" style="margin-top: 30px;">
            <h2 style="margin-bottom: 20px;">Riwayat Dokumen Diproses</h2>
            
<?php
            // PERBAIKAN: Tambahkan 'd.status AS doc_status' untuk memastikan kolom status terbaca
            $query2 = "SELECT d.*, d.status AS doc_status, u.nama_lengkap as pengaju_nama 
                      FROM documents d 
                      JOIN users u ON d.user_id = u.id 
                      WHERE d.status IN ('approved', 'rejected') 
                      ORDER BY d.updated_at DESC 
                      LIMIT 10";
            $result2 = $conn->query($query2);
            
            if ($result2->num_rows > 0):
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>No. Surat</th>
                            <th>Jenis</th>
                            <th>Pengaju</th>
                            <th>Tanggal Proses</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result2->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['nomor_surat']; ?></td>
                                <td><?php echo getJenisDokumen($row['jenis_dokumen']); ?></td>
                                <td><?php echo $row['pengaju_nama']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?></td>
                                <td>
                                    <?php 
                                    // PERBAIKAN: Gunakan 'doc_status' sesuai alias di query
                                    echo getStatusBadge($row['doc_status']); 
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">Belum ada dokumen yang diproses.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
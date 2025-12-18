<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/crypto.php';
require_once 'includes/functions.php';

requireRole('direksi');

$success = '';
$error = '';

$user_id = $_SESSION['user']['id'];
$keypair = getActiveKeyPair($conn, $user_id);

if (!$keypair) {
    $error = "Anda belum memiliki pasangan kunci. Silakan generate kunci terlebih dahulu di menu Generate Key.";
}

// Handle form submission (Proses Tanda Tangan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign']) && $keypair) {
    $doc_id = (int)$_POST['document_id'];
    $passphrase = $_POST['passphrase'];
    
    if (empty($passphrase)) {
        $error = "Harap masukkan Passphrase untuk membuka Private Key Anda.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND status = 'approved'");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        
        if ($document) {
            // =====================================================
            // ALUR KEAMANAN TINGGI (ENKRIPSI + METADATA SIGNING)
            // 1. Dekripsi file asli ke folder temp
            // 2. Stempel QR ke file temp
            // 3. Enkripsi file hasil stempel ke folder signed_docs/
            // 4. Sign Hash Metadata
            // 5. Hapus file temp
            // =====================================================
            
            $targetDir = 'signed_docs/';
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // --- PERSIAPAN FILE SEMENTARA ---
            $tempDir = sys_get_temp_dir();
            $encryptedSourcePath = $document['file_path']; // File di uploads/ (Terenkripsi)
            
            // 1. DEKRIPSI FILE ASLI
            $sourceContent = getDecryptedFileContent($encryptedSourcePath);
            
            if ($sourceContent === false) {
                $error = "Gagal mendekripsi file asli. Pastikan Key Enkripsi benar.";
            } else {
                // Simpan versi terdekripsi ke file sementara agar bisa dibaca library PDF
                $tempSourceFile = $tempDir . '/temp_src_' . uniqid() . '.pdf';
                file_put_contents($tempSourceFile, $sourceContent);
                
                // Siapkan path untuk output sementara (belum terenkripsi)
                $tempOutputFile = $tempDir . '/temp_out_' . uniqid() . '.pdf';
                
                // STEP 1: Generate QR & Stempel ke PDF (Gunakan file temp)
                $host = $_SERVER['HTTP_HOST']; 
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $qrContent = "$protocol://$host$base_path/verifikasi.php?code=" . urlencode($document['nomor_surat']);
                
                require_once 'includes/pdf_stamper.php';
                
                $stampSuccess = false;
                try {
                    // Gunakan tempSourceFile dan tempOutputFile
                    if (stampQrToPdf($tempSourceFile, $tempOutputFile, $qrContent, $document['nomor_surat'])) {
                        $stampSuccess = true;
                    } else {
                        $error = "Gagal menempelkan QR Code pada PDF.";
                    }
                } catch (Exception $e) {
                    $error = "Error PDF Library: " . $e->getMessage();
                }
                
                // STEP 2: Jika Stempel Berhasil, LANJUT PROSES
                if ($stampSuccess && file_exists($tempOutputFile)) {
                    
                    // A. ENKRIPSI HASIL AKHIR KE FOLDER TUJUAN
                    $finalFilename = basename($document['file_path']);
                    $encryptedDestPath = $targetDir . $finalFilename;
                    
                    try {
                        encryptFileStorage($tempOutputFile, $encryptedDestPath);
                        
                        // B. BUAT DIGEST DARI METADATA (Sesuai kode Anda)
                        $metadataString = $document['nomor_surat'] . '|' . 
                                        $document['nama_pengaju'] . '|' . 
                                        $document['jenis_dokumen'] . '|' . 
                                        $document['tanggal_mulai'];
                        
                        $digestToSign = hash('sha256', $metadataString); 
                        
                        // C. SIGN DIGEST DENGAN PRIVATE KEY
                        $signature = signDocument($digestToSign, $keypair['private_key'], $passphrase);
                        
                        if ($signature) {
                            // Simpan Signature ke Database
                            $stmt = $conn->prepare("INSERT INTO signatures (document_id, signer_id, document_hash, digital_signature) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("iiss", $doc_id, $_SESSION['user']['id'], $digestToSign, $signature);
                            
                            if ($stmt->execute()) {
                                logActivity($conn, $_SESSION['user']['id'], 'sign_document', "Menandatangani dokumen: " . $document['nomor_surat']);
                                $success = "✅ Dokumen berhasil ditandatangani dan dienkripsi aman!";
                            } else {
                                $error = "Gagal menyimpan signature ke database.";
                            }
                        } else {
                            $error = "⛔ Gagal membuat tanda tangan! Passphrase SALAH atau private key rusak.";
                        }

                    } catch (Exception $e) {
                        $error = "Gagal mengenkripsi file hasil: " . $e->getMessage();
                    }
                }
                
                // CLEANUP: Hapus file sementara (PENTING untuk keamanan)
                if (file_exists($tempSourceFile)) unlink($tempSourceFile);
                if (file_exists($tempOutputFile)) unlink($tempOutputFile);
            }
        } else {
            $error = "Dokumen tidak ditemukan atau belum disetujui.";
        }
    }
}

// Ambil daftar dokumen yang perlu ditandatangani
$query = "SELECT d.*, u.nama_lengkap as pengaju_nama 
          FROM documents d 
          JOIN users u ON d.user_id = u.id 
          LEFT JOIN signatures s ON d.id = s.document_id
          WHERE d.status = 'approved' AND s.id IS NULL
          ORDER BY d.updated_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanda Tangan Digital - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; 
            padding: 20px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .password-input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-close {
            background: #6c757d;
            float: right;
            margin-left: 10px;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin: 15px 0;
            font-size: 13px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>✍️ Tanda Tangan Digital</h1>
            <p>Tandatangani dokumen dengan RSA Digital Signature</p>
        </div>

        <div class="info-box">
            <strong>ℹ️ Cara Kerja Sistem Terenkripsi:</strong><br>
            1. Sistem mendekripsi file sementara & menempelkan QR Code<br>
            2. File final dienkripsi ulang (AES-256) agar aman di server<br>
            3. Metadata dokumen di-hash (SHA-256)<br>
            4. Hash Metadata di-sign dengan Private Key Anda
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($keypair): ?>
            <div class="table-container">
                <h2 style="margin-bottom: 20px;">Dokumen Menunggu Tanda Tangan</h2>
                
                <?php if ($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No. Surat</th>
                                <th>Jenis</th>
                                <th>Pengaju</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['nomor_surat']); ?></strong></td>
                                    <td><?php echo getJenisDokumen($row['jenis_dokumen']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pengaju_nama']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="openSignModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nomor_surat']); ?>')">
                                            ✍️ Proses TTD
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">✅ Tidak ada dokumen yang perlu ditandatangani saat ini.</div>
                <?php endif; ?>
            </div>

            <div class="table-container" style="margin-top: 30px;">
                <h2 style="margin-bottom: 20px;">Riwayat Tanda Tangan</h2>
                <?php
                $query2 = "SELECT d.nomor_surat, d.jenis_dokumen, d.file_path, u.nama_lengkap as pengaju_nama, s.signed_at
                          FROM signatures s
                          JOIN documents d ON s.document_id = d.id
                          JOIN users u ON d.user_id = u.id
                          WHERE s.signer_id = ?
                          ORDER BY s.signed_at DESC LIMIT 10";
                $stmt = $conn->prepare($query2);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result2 = $stmt->get_result();
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>No. Surat</th>
                            <th>Tanggal TTD</th>
                            <th>Aksi</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result2->num_rows > 0): ?>
                            <?php while ($row = $result2->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nomor_surat']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['signed_at'])); ?></td>
                                    <td>
                                        <?php 
                                            // Ambil nama file saja
                                            $filename = basename($row['file_path']);
                                            
                                            // Cek keberadaan file (meski terenkripsi, file fisik harus ada)
                                            $signedPath = "signed_docs/" . $filename;
                                        ?>
                                        <?php if (file_exists($signedPath)): ?>
                                            <a href="download.php?source=signed&file=<?php echo $filename; ?>" target="_blank" class="btn btn-success btn-sm">📥 Download</a>
                                        <?php else: ?>
                                            <span class="badge badge-warning">File hilang</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center;">Belum ada riwayat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">
                ⚠️ Anda belum memiliki kunci keamanan. Silakan generate kunci terlebih dahulu.
            </div>
        <?php endif; ?>
    </div>

    <div id="signModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top: 0;">🔒 Unlock Private Key</h3>
            <p>Masukkan <strong>Passphrase</strong> untuk membuka private key terenkripsi Anda.</p>
            <p style="font-size: 13px; color: #666;">Dokumen: <span id="docNomor" style="font-weight:bold;"></span></p>
            
            <form method="POST" action="">
                <input type="hidden" name="document_id" id="modalDocId">
                <input type="password" name="passphrase" class="password-input" placeholder="Masukkan Passphrase Private Key..." required autofocus autocomplete="off">
                
                <div class="info-box" style="font-size: 12px;">
                    Passphrase ini hanya digunakan untuk membuka private key yang terenkripsi di database. Setelah di-unlock, private key akan digunakan untuk sign hash dokumen.
                </div>

                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" class="btn btn-close" onclick="closeSignModal()">Batal</button>
                    <button type="submit" name="sign" class="btn btn-primary">✅ Unlock & Sign</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openSignModal(id, nomor) {
            document.getElementById('modalDocId').value = id;
            document.getElementById('docNomor').innerText = nomor;
            document.getElementById('signModal').style.display = "block";
        }
        
        function closeSignModal() {
            document.getElementById('signModal').style.display = "none";
        }

        window.onclick = function(event) {
            var modal = document.getElementById('signModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
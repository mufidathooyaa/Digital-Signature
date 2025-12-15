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
            // ALUR BENAR: 
            // 1. Stempel QR ke PDF → File Final
            // 2. Hash File Final
            // 3. Passphrase = Unlock Private Key (yang terenkripsi)
            // 4. Sign Hash dengan Private Key (yang sudah di-unlock)
            // =====================================================
            
            $targetDir = 'signed_docs/';
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // STEP 1: Generate QR & Stempel ke PDF
            $host = $_SERVER['HTTP_HOST']; 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $qrContent = "$protocol://$host$base_path/verifikasi.php?code=" . urlencode($document['nomor_surat']);
            
            require_once 'includes/pdf_stamper.php';
            
            $sourcePdf = $document['file_path'];
            $outputPdf = $targetDir . basename($sourcePdf);
            
            $stampSuccess = false;
            try {
                if (stampQrToPdf($sourcePdf, $outputPdf, $qrContent, $document['nomor_surat'])) {
                    $stampSuccess = true;
                } else {
                    $error = "Gagal menempelkan QR Code pada PDF.";
                }
            } catch (Exception $e) {
                $error = "Error PDF Library: " . $e->getMessage();
            }
            
            // STEP 2: Jika Stempel Berhasil, HASH FILE FINAL (yang sudah ada QR)
            if ($stampSuccess && file_exists($outputPdf)) {
                
                $finalFileHash = hash_file('sha256', $outputPdf);
                
                // STEP 3 & 4: 
                // - Passphrase digunakan untuk UNLOCK private key yang terenkripsi
                // - Private key yang di-unlock kemudian dipakai untuk SIGN hash
                // Fungsi signDocument() sudah menangani ini di crypto.php
                
                $signature = signDocument($finalFileHash, $keypair['private_key'], $passphrase);
                
                if ($signature) {
                    // Simpan Signature ke Database
                    $stmt = $conn->prepare("INSERT INTO signatures (document_id, signer_id, document_hash, digital_signature) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiss", $doc_id, $_SESSION['user']['id'], $finalFileHash, $signature);
                    
                    if ($stmt->execute()) {
                        logActivity($conn, $_SESSION['user']['id'], 'sign_document', "Menandatangani dokumen: " . $document['nomor_surat']);
                        $success = "✅ Dokumen berhasil ditandatangani! Hash file final telah di-sign dengan private key Anda.";
                    } else {
                        $error = "Gagal menyimpan signature ke database.";
                    }
                } else {
                    $error = "⛔ Gagal membuat tanda tangan! Passphrase SALAH atau private key rusak.";
                }
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
            <strong>ℹ️ Cara Kerja:</strong><br>
            1. Sistem menempelkan QR Code ke PDF<br>
            2. File final di-hash dengan SHA-256<br>
            3. Passphrase membuka private key terenkripsi<br>
            4. Hash di-sign dengan private key menggunakan RSA
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
                                            $filename = basename($row['file_path']);
                                            $signedPath = "signed_docs/" . $filename;
                                        ?>
                                        <?php if (file_exists($signedPath)): ?>
                                            <a href="<?php echo $signedPath; ?>" target="_blank" class="btn btn-success btn-sm">📥 Download</a>
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
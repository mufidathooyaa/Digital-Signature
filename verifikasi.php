<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/crypto.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$result = null;
$error = '';
$nomor_surat = '';
$verification_mode = ''; 

// LOGIKA UTAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['code'])) {
    
    if (isset($_POST['verify_file'])) {
        $verification_mode = 'accurate';
        $nomor_surat = sanitizeInput($_POST['nomor_surat']);
    } else {
        $verification_mode = 'quick';
        if (isset($_POST['nomor_surat'])) {
            $nomor_surat = sanitizeInput($_POST['nomor_surat']);
        } else if (isset($_GET['code'])) {
            $nomor_surat = sanitizeInput($_GET['code']);
        }
    }

    if (!empty($nomor_surat)) {
        $stmt = $conn->prepare("SELECT d.*, s.signer_id, u.nama_lengkap as nama_penanda_tangan, s.signed_at
                               FROM documents d
                               LEFT JOIN signatures s ON d.id = s.document_id
                               LEFT JOIN users u ON s.signer_id = u.id
                               WHERE d.nomor_surat = ?");
        $stmt->bind_param("s", $nomor_surat);
        $stmt->execute();
        $document = $stmt->get_result()->fetch_assoc();
        
        if ($document) {
            $result = array('document' => $document);
            
            if ($document['signed_at']) {
                
                // =======================================================
                // VERIFIKASI DIGITAL SIGNATURE (SECURE MODE):
                // 1. Hash file upload
                // 2. Rekonstruksi string data ($nomor_surat | $fileHash)
                // 3. Verifikasi signature terhadap string rekonstruksi
                // =======================================================
                
                if ($verification_mode === 'accurate') {
                    if (isset($_FILES['dokumen_upload']) && $_FILES['dokumen_upload']['error'] === 0) {
                        
                        // 1. Hitung Hash File yang Diupload User
                        $uploadedFileHash = hash_file('sha256', $_FILES['dokumen_upload']['tmp_name']);
                        
                        // 2. Ambil Signature dari Database
                        $stmt2 = $conn->prepare("SELECT digital_signature FROM signatures WHERE document_id = ?");
                        $stmt2->bind_param("i", $document['id']);
                        $stmt2->execute();
                        $signature_data = $stmt2->get_result()->fetch_assoc();
                        
                        if ($signature_data) {
                            // 3. Ambil PUBLIC KEY Penanda Tangan
                            $stmt3 = $conn->prepare("SELECT public_key FROM keypairs WHERE user_id = ? AND status = 'active' ORDER BY generated_at DESC LIMIT 1");
                            $stmt3->bind_param("i", $document['signer_id']);
                            $stmt3->execute();
                            $key_result = $stmt3->get_result()->fetch_assoc();
                            
                            if ($key_result) {
                                // 4. REKONSTRUKSI DATA (PENTING!)
                                // Kita harus menyusun ulang data persis seperti saat ditandatangani di tanda_tangan.php
                                // Format: [Nomor Surat] + "|" + [Hash File]
                                $dataToVerify = $document['nomor_surat'] . '|' . $uploadedFileHash;
                                $reconstructedHash = hash('sha256', $dataToVerify);
                                
                                // 5. VERIFIKASI SIGNATURE
                                // Cek apakah signature valid untuk hash yang KITA HITUNG SENDIRI (bukan dari DB)
                                $isSignatureValid = verifySignature(
                                    $reconstructedHash, 
                                    $signature_data['digital_signature'], 
                                    $key_result['public_key']
                                );
                                
                                // 6. HASIL VERIFIKASI
                                if ($isSignatureValid) {
                                    $result['verification'] = array(
                                        'status' => 'valid',
                                        'message' => 'DOKUMEN OTENTIK! Signature valid & isi dokumen tidak berubah.'
                                    );
                                } else {
                                    // Jika signature tidak valid, ada 2 kemungkinan:
                                    // a. File telah dimodifikasi (Hash file berubah -> Reconstructed Hash berubah)
                                    // b. Signature memang palsu
                                    $result['verification'] = array(
                                        'status' => 'invalid',
                                        'message' => 'DOKUMEN TIDAK VALID! File telah dimodifikasi atau tanda tangan palsu.'
                                    );
                                }
                                
                                // Debugging info (Optional)
                                $result['debug'] = array(
                                    'uploaded_hash' => $uploadedFileHash,
                                    'reconstructed_data_hash' => $reconstructedHash,
                                    'signature_valid' => $isSignatureValid
                                );

                            } else {
                                $result['verification'] = array(
                                    'status' => 'error',
                                    'message' => 'Public key penanda tangan tidak ditemukan.'
                                );
                            }
                        } else {
                            $result['verification'] = array(
                                'status' => 'error',
                                'message' => 'Data signature tidak ditemukan di database.'
                            );
                        }
                    } else {
                        $error = "Gagal mengupload file.";
                    }
                }
                
                else {
                    // MODE CEPAT: Cek Nomor Surat Saja (Administratif)
                    // Mode ini TIDAK menjamin isi file asli, hanya menjamin nomor surat pernah ditandatangani.
                    
                    $stmt2 = $conn->prepare("SELECT digital_signature, document_hash FROM signatures WHERE document_id = ?");
                    $stmt2->bind_param("i", $document['id']);
                    $stmt2->execute();
                    $signature_data = $stmt2->get_result()->fetch_assoc();
                    
                    if ($signature_data) {
                        $stmt3 = $conn->prepare("SELECT public_key FROM keypairs WHERE user_id = ? AND status = 'active' ORDER BY generated_at DESC LIMIT 1");
                        $stmt3->bind_param("i", $document['signer_id']);
                        $stmt3->execute();
                        $key_result = $stmt3->get_result()->fetch_assoc();
                        
                        if ($key_result) {
                            // Verifikasi Signature terhadap Hash yang tersimpan di DB
                            $isSignatureValid = verifySignature(
                                $signature_data['document_hash'], 
                                $signature_data['digital_signature'], 
                                $key_result['public_key']
                            );
                            
                            if ($isSignatureValid) {
                                $result['verification'] = array(
                                    'status' => 'valid_db', 
                                    'message' => 'Nomor Surat Terdaftar & Tanda Tangan Administratif Valid.'
                                );
                            } else {
                                $result['verification'] = array(
                                    'status' => 'invalid', 
                                    'message' => 'Nomor surat terdaftar, tetapi SIGNATURE TIDAK VALID!'
                                );
                            }
                        } else {
                            $result['verification'] = array(
                                'status' => 'error', 
                                'message' => 'Public key tidak ditemukan.'
                            );
                        }
                    } else {
                        $result['verification'] = array(
                            'status' => 'pending', 
                            'message' => 'Nomor surat terdaftar, tetapi belum ditandatangani.'
                        );
                    }
                }
                
                $result['signer'] = ['nama_lengkap' => $document['nama_penanda_tangan']];

            } else {
                $result['verification'] = array(
                    'status' => 'pending', 
                    'message' => 'Dokumen ini belum ditandatangani oleh Direksi.'
                );
                $result['signer'] = ['nama_lengkap' => '-'];
            }
        } else {
            $error = "Nomor Surat <strong>$nomor_surat</strong> tidak ditemukan.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - Sistem Digital Signature</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn { padding: 10px 20px; background: none; border: none; cursor: pointer; font-size: 16px; font-weight: 600; color: #666; }
        .tab-btn.active { color: #2563eb; border-bottom: 2px solid #2563eb; margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .upload-box { border: 2px dashed #cbd5e1; padding: 30px; text-align: center; border-radius: 8px; margin-bottom: 15px; background: #f8fafc; }
        .alert-warning-sig { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .debug-box { background: #f3f4f6; border: 1px solid #d1d5db; padding: 10px; margin: 15px 0; font-size: 12px; font-family: monospace; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔍 Verifikasi Dokumen</h1>
            <p>Verifikasi keaslian dokumen menggunakan RSA Digital Signature</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="tabs">
                <button class="tab-btn <?php echo ($verification_mode !== 'accurate') ? 'active' : ''; ?>" onclick="switchTab('quick')">⚡ Cek Nomor (Cepat)</button>
                <button class="tab-btn <?php echo ($verification_mode === 'accurate') ? 'active' : ''; ?>" onclick="switchTab('accurate')">🛡️ Cek File (Akurat)</button>
            </div>

            <div id="quick" class="tab-content <?php echo ($verification_mode !== 'accurate') ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <p style="margin-bottom: 15px; color: #666;">Cek validitas nomor surat dan verifikasi signature di database.</p>
                    <div class="form-group">
                        <label>Nomor Surat</label>
                        <input type="text" name="nomor_surat" required placeholder="Contoh: KEU/20251203/0001" 
                               value="<?php echo htmlspecialchars($nomor_surat); ?>">
                    </div>
                    <button type="submit" name="verify_quick" class="btn btn-primary">🔍 Verifikasi Signature</button>
                </form>
            </div>

            <div id="accurate" class="tab-content <?php echo ($verification_mode === 'accurate') ? 'active' : ''; ?>">
                <form method="POST" action="" enctype="multipart/form-data">
                    <p style="margin-bottom: 15px; color: #666;">Upload file PDF final (yang sudah ada QR Code) untuk verifikasi lengkap.</p>
                    
                    <div class="form-group">
                        <label>1. Masukkan Nomor Surat</label>
                        <input type="text" name="nomor_surat" required placeholder="Contoh: KEU/20251203/0001"
                               value="<?php echo htmlspecialchars($nomor_surat); ?>">
                    </div>

                    <div class="form-group">
                        <label>2. Upload File PDF (yang sudah ditandatangani)</label>
                        <div class="upload-box">
                            <input type="file" name="dokumen_upload" accept=".pdf" required style="width: 100%;">
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_file" class="btn btn-primary" style="background: #059669;">🛡️ Validasi File & Signature</button>
                </form>
            </div>
        </div>
        
        <?php if ($result): ?>
            <div class="form-container" style="margin-top: 30px;">
                
                <?php if ($result['verification']['status'] === 'valid'): ?>
                    <div class="alert alert-success" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                        <strong>DOKUMEN OTENTIK</strong><br>
                        <span style="font-size: 14px; font-weight: normal;">Digital signature valid & hash file cocok.</span>
                    </div>

                <?php elseif ($result['verification']['status'] === 'valid_db'): ?>
                    <div class="alert alert-success" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                        <strong>SIGNATURE TERVERIFIKASI (DB)</strong><br>
                        <span style="font-size: 14px; font-weight: normal;">Nomor surat resmi & signature valid dengan public key.</span>
                    </div>

                <?php elseif ($result['verification']['status'] === 'invalid'): ?>
                    <div class="alert alert-danger" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">❌</div>
                        <strong>DOKUMEN TIDAK VALID / DIMODIFIKASI</strong><br>
                        <span style="font-size: 14px;">Isi file berbeda dengan yang ditandatangani atau signature palsu.</span>
                    </div>
                
                <?php else: ?>
                    <div class="alert alert-warning" style="text-align: center;">
                        ⚠️ <?php echo $result['verification']['message']; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($result['debug']) && isset($_SESSION['user'])): ?>
                <div class="debug-box">
                    <strong>Debug Info (For logged-in users):</strong><br>
                    Uploaded Hash: <?php echo substr($result['debug']['uploaded_hash'], 0, 32); ?>...<br>
                    Reconstructed Hash: <?php echo substr($result['debug']['reconstructed_data_hash'], 0, 32); ?>...<br>
                    Signature Valid: <?php echo $result['debug']['signature_valid'] ? 'YES' : 'NO'; ?>
                </div>
                <?php endif; ?>

                <?php if ($result['verification']['status'] !== 'error'): ?>
                <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <h3>Detail Dokumen</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td style="padding: 10px; font-weight: 600; width: 180px;">Penanda Tangan:</td>
                            <td style="padding: 10px;">
                                <?php echo isset($result['signer']) ? $result['signer']['nama_lengkap'] : '-'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: 600;">Waktu Tanda Tangan:</td>
                            <td style="padding: 10px;">
                                <?php 
                                echo ($result['document']['signed_at']) ? date('d F Y, H:i:s', strtotime($result['document']['signed_at'])) : '-'; 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: 600;">Jenis Dokumen:</td>
                            <td style="padding: 10px;"><?php echo getJenisDokumen($result['document']['jenis_dokumen']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: 600;">Keterangan:</td>
                            <td style="padding: 10px;"><?php echo htmlspecialchars($result['document']['keterangan']); ?></td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <strong>📄 File Arsip Resmi:</strong><br>
                        
                        <?php if (isset($_SESSION['user'])): ?>
                            <?php 
                                $nama_file = basename($result['document']['file_path']);
                            ?>
                            <div style="margin-top: 10px;">
                                <a href="download.php?source=signed&file=<?php echo urlencode($nama_file); ?>" target="_blank" class="btn btn-primary btn-sm">
                                    📥 Download File Asli
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 10px; color: #555; font-size: 0.9em; border-left: 3px solid #2563eb; padding-left: 10px;">
                                🔒 <em>Unduhan file asli hanya tersedia untuk staf yang login.</em>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.target.classList.add('active');
    }
    </script>
</body>
</html>
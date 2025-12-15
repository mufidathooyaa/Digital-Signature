<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/crypto.php';
require_once 'includes/functions.php';

// 1. NONAKTIFKAN requireLogin() AGAR PUBLIK BISA AKSES
// requireLogin(); 

// Pastikan session aktif untuk mengecek status login nanti
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$result = null;
$error = '';
$nomor_surat = '';
$verification_mode = ''; 

// LOGIKA UTAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['code'])) {
    
    // TENTUKAN MODE DAN NOMOR SURAT
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
        // Ambil Data Dokumen dari Database
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
            
            // Cek apakah dokumen sudah ditandatangani
            if ($document['signed_at']) {
                
                // ==========================================
                // LOGIKA VERIFIKASI KETAT (HANYA FILE FINAL)
                // ==========================================
                
                if ($verification_mode === 'accurate') {
                    // Cek File Upload User
                    if (isset($_FILES['dokumen_upload']) && $_FILES['dokumen_upload']['error'] === 0) {
                        
                        $serverSignedPath = 'signed_docs/' . basename($document['file_path']);
                        
                        if (file_exists($serverSignedPath)) {
                            // Hitung Hash
                            $userFileHash = hash_file('sha256', $_FILES['dokumen_upload']['tmp_name']);
                            $serverFileHash = hash_file('sha256', $serverSignedPath);
                            
                            // Bandingkan
                            if ($userFileHash === $serverFileHash) {
                                $result['verification'] = array(
                                    'status' => 'valid',
                                    'message' => 'Dokumen 100% Identik dengan Arsip Resmi.'
                                );
                            } else {
                                $result['verification'] = array(
                                    'status' => 'invalid',
                                    'message' => 'Isi file berbeda dengan arsip resmi server. Dokumen mungkin palsu, rusak, atau masih berupa draft.'
                                );
                            }
                        } else {
                            $result['verification'] = array(
                                'status' => 'error',
                                'message' => 'File arsip resmi tidak ditemukan di server (Hubungi Administrator).'
                            );
                        }
                    } else {
                        $error = "Gagal mengupload file.";
                    }
                } else {
                    // MODE CEPAT (Cek Database Saja)
                    $result['verification'] = array(
                        'status' => 'valid_db', 
                        'message' => 'Nomor Surat Terdaftar Resmi.'
                    );
                }
                
                $result['signer'] = ['nama_lengkap' => $document['nama_penanda_tangan']];

            } else {
                // KASUS BELUM DITANDATANGANI
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
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>🔍 Verifikasi Dokumen</h1>
            <p>Pastikan keaslian dokumen Anda di sini</p>
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
                    <p style="margin-bottom: 15px; color: #666;">Cek validitas nomor surat yang tertera pada dokumen.</p>
                    <div class="form-group">
                        <label>Nomor Surat</label>
                        <input type="text" name="nomor_surat" required placeholder="Contoh: KEU/20251203/0001" 
                               value="<?php echo htmlspecialchars($nomor_surat); ?>">
                    </div>
                    <button type="submit" name="verify_quick" class="btn btn-primary">🔍 Cari Dokumen</button>
                </form>
            </div>

            <div id="accurate" class="tab-content <?php echo ($verification_mode === 'accurate') ? 'active' : ''; ?>">
                <form method="POST" action="" enctype="multipart/form-data">
                    <p style="margin-bottom: 15px; color: #666;">Upload file PDF yang sudah bertanda tangan (QR Code) untuk dicek keasliannya.</p>
                    
                    <div class="form-group">
                        <label>1. Masukkan Nomor Surat</label>
                        <input type="text" name="nomor_surat" required placeholder="Contoh: KEU/20251203/0001"
                               value="<?php echo htmlspecialchars($nomor_surat); ?>">
                    </div>

                    <div class="form-group">
                        <label>2. Upload File PDF</label>
                        <div class="upload-box">
                            <input type="file" name="dokumen_upload" accept=".pdf" required style="width: 100%;">
                        </div>
                    </div>
                    
                    <button type="submit" name="verify_file" class="btn btn-primary" style="background: #059669;">🛡️ Validasi File</button>
                </form>
            </div>
        </div>
        
        <?php if ($result): ?>
            <div class="form-container" style="margin-top: 30px;">
                
                <?php if ($result['verification']['status'] === 'valid'): ?>
                    <div class="alert alert-success" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                        <strong>DOKUMEN OTENTIK</strong><br>
                        <span style="font-size: 14px; font-weight: normal;">(File yang Anda upload 100% SAMA dengan arsip resmi di server kami)</span>
                    </div>

                <?php elseif ($result['verification']['status'] === 'valid_db'): ?>
                    <div class="alert alert-success" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                        <strong>NOMOR SURAT RESMI</strong><br>
                        <span style="font-size: 14px; font-weight: normal;">(Surat tercatat di sistem. Mohon cek fisik dokumen dengan file asli di bawah)</span>
                    </div>

                <?php elseif ($result['verification']['status'] === 'invalid'): ?>
                    <div class="alert alert-danger" style="font-size: 18px; padding: 25px; text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 10px;">❌</div>
                        <strong>DOKUMEN TIDAK COCOK</strong><br>
                        <span style="font-size: 14px;">File upload berbeda dengan arsip resmi. Pastikan Anda mengupload file final yang sudah memiliki QR Code.</span>
                    </div>
                
                <?php else: ?>
                    <div class="alert alert-warning" style="text-align: center;">
                        ⚠️ <?php echo $result['verification']['message']; ?>
                    </div>
                <?php endif; ?>

                <?php if ($result['verification']['status'] !== 'error'): ?>
                <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
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
                            <td style="padding: 10px;"><?php echo $result['document']['keterangan']; ?></td>
                        </tr>
                    </table>
                    
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <strong>📄 File Arsip Resmi:</strong><br>
                        
                        <?php if (isset($_SESSION['user'])): ?>
                            <?php 
                                // Ambil nama file saja, bukan path lengkap, karena download.php akan menambahkan foldernya
                                $nama_file = basename($result['document']['file_path']);
                            ?>
                            <div style="margin-top: 10px;">
                                <a href="download.php?source=signed&file=<?php echo $nama_file; ?>" target="_blank" class="btn btn-primary btn-sm">
                                    📥 Download File Asli (Internal)
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 10px; color: #555; font-size: 0.9em; border-left: 3px solid #2563eb; padding-left: 10px;">
                                🔒 <em>Untuk alasan keamanan dan privasi data perusahaan, unduhan file asli hanya tersedia untuk staf yang login.<br>
                                Silakan bandingkan fisik dokumen yang Anda pegang dengan data validasi di atas.</em>
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
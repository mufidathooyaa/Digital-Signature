<?php
// Fungsi untuk format tanggal Indonesia
function formatTanggalIndo($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

// Fungsi untuk generate nomor surat otomatis
function generateNomorSurat($conn, $kode) {
    // 1. Tentukan Prefix
    $prefixMap = [
        'dana' => 'KEU',
        'tugas' => 'ST',
        'bast' => 'BAST',
    ];
    $prefix = isset($prefixMap[$kode]) ? $prefixMap[$kode] : strtoupper($kode);
    
    // 2. Tentukan Tanggal (Format YYYYMMDD)
    $tanggal = date('Ymd');
    
    // 3. Cari Nomor Terakhir di Database untuk Prefix & Tanggal ini
    // Pola pencarian: KEU/20251212/%
    $searchPattern = $prefix . '/' . $tanggal . '/%';
    
    $stmt = $conn->prepare("SELECT nomor_surat FROM documents WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $searchPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 4. Hitung Nomor Urut Baru
    $newCounter = 1; // Default mulai dari 1
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNomor = $row['nomor_surat'];
        
        // Pecah string nomor surat berdasarkan '/'
        // Contoh: KEU/20251212/0005 -> ambil "0005"
        $parts = explode('/', $lastNomor);
        $lastCount = end($parts);
        
        // Tambah 1
        $newCounter = intval($lastCount) + 1;
    }
    
    // 5. Format dengan padding nol (misal: 1 jadi 0001)
    $counterStr = str_pad($newCounter, 4, '0', STR_PAD_LEFT);
    
    // Hasil: KEU/20251212/0001
    return $prefix . '/' . $tanggal . '/' . $counterStr;
}

// Fungsi untuk upload file
function uploadFile($file, $targetDir = 'uploads/') {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetFile = $targetDir . $fileName;
    
    // --- PERBAIKAN DI SINI ---
    // Definisikan $fileType dengan mengambil ekstensi file
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Opsi Tambahan: Validasi MIME Type agar lebih aman (menggantikan logika finfo yang belum selesai)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Cek apakah file adalah PDF (berdasarkan ekstensi)
    if ($fileType != "pdf") {
        return array('success' => false, 'message' => 'Hanya file PDF yang diperbolehkan (Ekstensi salah)');
    }

    // Cek apakah file adalah PDF (berdasarkan MIME type - lebih aman)
    if ($mime != 'application/pdf') {
        return array('success' => false, 'message' => 'File bukan PDF yang valid');
    }
    
    // Cek ukuran file (max 5MB)
    if ($file['size'] > 5000000) {
        return array('success' => false, 'message' => 'Ukuran file terlalu besar (max 5MB)');
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return array('success' => true, 'file_path' => $targetFile);
    }
    
    return array('success' => false, 'message' => 'Upload file gagal');
}

// Fungsi untuk mendapatkan status badge
function getStatusBadge($status) {
    $badges = array(
        'pending' => '<span class="badge badge-warning">Menunggu</span>',
        'approved' => '<span class="badge badge-success">Disetujui</span>',
        'rejected' => '<span class="badge badge-danger">Ditolak</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : $status;
}

// Fungsi untuk mendapatkan nama jenis dokumen
function getJenisDokumen($jenis) {
    $jenisList = array(
        'dana' => 'Pengajuan Dana',
        'tugas' => 'Surat Tugas',
        'bast' => 'Berita Acara Serah Terima',
    );
    
    return isset($jenisList[$jenis]) ? $jenisList[$jenis] : $jenis;
}

// Fungsi untuk validasi input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk mendapatkan user by ID
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, username, nama_lengkap, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function logActivity($conn, $user_id, $action, $description) {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $user_id, $action, $description);
    $stmt->execute();
}

// Di includes/functions.php
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Fungsi Cerdas: Membaca PDF dan mengambil data spesifik (Targeted Extraction)
 * Digunakan untuk mengambil "Isi Vital" dokumen agar ikut di-hash.
 */
function extractSpecificData($filePath, $jenis_dokumen) {
    // 1. Load Library
    $autoloadPath = __DIR__ . '/../libs/fpdi/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        return []; 
    }
    
    $extractedData = [];
    $fullText = '';

    try {
        // 2. Baca PDF
        $stream = \setasign\Fpdi\PdfParser\StreamReader::createByFile($filePath);
        $parser = new \setasign\Fpdi\PdfParser\PdfParser($stream);
        $reader = new \setasign\Fpdi\PdfReader\PdfReader($parser);
        $page = $reader->getPage(1);
        $contentStream = $page->getContentStream();
        
        // 3. Ambil Semua Teks
        if (preg_match_all('/\((.*?)\)/', $contentStream, $matches)) {
            $fullText = implode(" ", $matches[1]);
            // Normalisasi spasi (ubah enter/tab jadi spasi tunggal)
            $fullText = preg_replace('/\s+/', ' ', trim($fullText)); 
        }

        // 4. LOGIKA PENGAMBILAN RINCIAN (BODY CONTENT)
        
        // --- KASUS A: PENGAJUAN DANA ---
        if ($jenis_dokumen == 'pengajuan_dana') {
            // Ambil Nama Pemohon
            if (preg_match('/Nama Pemohon\s*[:]?\s*([A-Za-z\s\.]+)/i', $fullText, $m)) {
                $extractedData['pemohon'] = trim($m[1]);
            }
            
            // Ambil Nominal Total
            if (preg_match('/TOTAL PENGAJUAN.*?(Rp\s*[\d\.,]+)/i', $fullText, $m)) {
                $extractedData['total'] = $m[1]; 
            }

            // [PENTING] Ambil Seluruh Blok Rincian (Tabel)
            // Teks berada di antara kata "Keterangan Keperluan" (Header Tabel) dan "TOTAL PENGAJUAN" (Footer)
            // Ini akan mengunci: "1. Beli Server Rp 10jt, 2. Beli Kabel Rp 5jt"
            if (preg_match('/Keterangan Keperluan.*?\s*Jumlah\s*\(Rp\)\s*(.*?)TOTAL PENGAJUAN/i', $fullText, $m)) {
                // Kita bersihkan karakter non-alphanumeric agar hash stabil
                $cleanRincian = preg_replace('/[^a-zA-Z0-9]/', '', $m[1]);
                $extractedData['rincian_hash'] = $cleanRincian;
            }
        }
        
        // --- KASUS B: SURAT TUGAS ---
        elseif ($jenis_dokumen == 'kegiatan_dinas' || $jenis_dokumen == 'surat_tugas') {
            // Ambil Petugas
            if (preg_match('/MEMBERI TUGAS KEPADA.*?Nama\s*[:]?\s*([A-Za-z\s\.]+)/i', $fullText, $m)) {
                $extractedData['petugas'] = trim($m[1]);
            }
            
            // [PENTING] Ambil Isi Tugas
            // Teks antara "UNTUK:" dan "Demikian surat tugas"
            if (preg_match('/UNTUK\s*[:]\s*(.*?)Demikian/i', $fullText, $m)) {
                 $cleanTugas = preg_replace('/[^a-zA-Z0-9]/', '', $m[1]);
                 $extractedData['isi_tugas'] = $cleanTugas;
            }
        }
        
        // --- KASUS C: BERITA ACARA (BAST) ---
        elseif ($jenis_dokumen == 'bast') {
            // [PENTING] Ambil Daftar Barang
            // Teks antara "hal-hal sebagai berikut" dan "Demikian"
            if (preg_match('/hal-hal sebagai berikut\s*[:]\s*(.*?)Demikian/i', $fullText, $m)) {
                $cleanBarang = preg_replace('/[^a-zA-Z0-9]/', '', $m[1]);
                $extractedData['daftar_barang'] = $cleanBarang;
            }
        }
        
        // GLOBAL: Nomor Surat
        if (preg_match('/Nomor\s*[:\.]\s*\[\s*(.*?)\s*\]/i', $fullText, $m)) {
            $extractedData['nomor_surat'] = trim($m[1]);
        }

    } catch (Exception $e) {
        error_log("Gagal ekstrak PDF: " . $e->getMessage());
        // Fallback: Jika gagal baca teks, kembalikan hash file utuh di level crypto.php
    }
    
    return $extractedData;
}
?>
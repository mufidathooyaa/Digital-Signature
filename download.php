<?php
// FILE: download.php (UPDATE LENGKAP)
require_once 'config/database.php';
require_once 'includes/auth.php';

// 1. WAJIB LOGIN untuk mendownload file apapun
requireLogin(); 

if (isset($_GET['source']) && isset($_GET['file'])) {
    $source = $_GET['source'];
    $filename = basename($_GET['file']); // 'basename' mencegah hacker akses folder lain (../)
    
    // 2. DAFTAR FOLDER YANG DIIZINKAN (Whitelist)
    $allowed_paths = [
        'uploads'   => 'uploads/',
        'signed'    => 'signed_docs/',
        'template'  => 'templates/'
    ];

    if (array_key_exists($source, $allowed_paths)) {
        $filepath = $allowed_paths[$source] . $filename;
        
        // ... Kode validasi path sebelumnya (whitelist folder dsb) ...

        if (file_exists($filepath)) {
            // Deteksi tipe file
            $mime = mime_content_type($filepath); // Ini mungkin mendeteksi 'application/octet-stream' karena terenkripsi
            
            // Override mime jika PDF (karena file terenkripsi tidak dikenali sebagai PDF oleh server)
            if (strpos($filename, '.pdf') !== false) {
                $mime = 'application/pdf';
            } elseif (strpos($filename, '.docx') !== false) {
                $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            // Header Download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // --- PROSES DEKRIPSI ---
            // Jangan pakai readfile(), tapi dekripsi dulu
            require_once 'includes/functions.php'; // Pastikan functions diload
            
            $decryptedContent = getDecryptedFileContent($filepath);
            
            header('Content-Length: ' . strlen($decryptedContent));
            echo $decryptedContent;
            exit;
        } 
        else {
            die("Error: File tidak ditemukan di server.");
        }
    } 
    else {
        die("Error: Sumber file tidak valid.");
    }
} 
else {
    die("Error: Parameter tidak lengkap.");
}
?>
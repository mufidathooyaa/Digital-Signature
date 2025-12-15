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
        
        if (file_exists($filepath)) {
            // Deteksi tipe file otomatis (PDF/Docx/dll)
            $mime = mime_content_type($filepath);
            
            // Kirim Header agar browser mengerti ini file download/preview
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filepath));
            
            // Baca file yang dikunci tadi dan kirim ke user
            readfile($filepath);
            exit;
        } else {
            die("Error: File tidak ditemukan di server.");
        }
    } else {
        die("Error: Sumber file tidak valid.");
    }
} else {
    die("Error: Parameter tidak lengkap.");
}
?>
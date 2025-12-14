<?php
// FILE: download.php
require_once 'includes/auth.php';

requireLogin(); 

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    
    // Mapping file template
    $templates = [
        'dana' => [
            'file' => 'templates/Template_Pengajuan_Dana.docx',
            'name' => 'Template_Pengajuan_Dana.docx'
        ],
        'tugas' => [
            'file' => 'templates/Template_Surat_Tugas.docx',
            'name' => 'Template_Surat_Tugas.docx'
        ],
        'bast' => [
            'file' => 'templates/Template_BAST.docx',
            'name' => 'Template_Berita_Acara.docx'
        ]
    ];

    if (isset($templates[$type])) {
        $fileConfig = $templates[$type];
        $targetFile = $fileConfig['file'];
        
        if (file_exists($targetFile)) {
            // Force download header
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . $fileConfig['name'] . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($targetFile));
            
            readfile($targetFile);
            exit;
        } else {
            die("Error: File template tidak ditemukan di server.");
        }
    } else {
        die("Error: Jenis template tidak valid.");
    }
}
?>
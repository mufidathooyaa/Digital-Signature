<?php
// FILE: encrypt_templates.php
require_once 'config/database.php';
require_once 'includes/functions.php';

$folder = 'templates/';
$files = scandir($folder);

echo "<h1>Enkripsi Template</h1>";

foreach ($files as $file) {
    if ($file == '.' || $file == '..' || $file == '.htaccess') continue;
    
    $path = $folder . $file;
    
    // Cek apakah file sudah terenkripsi atau belum (sederhana: cek apakah bisa dibaca normal)
    // PERINGATAN: Pastikan ini hanya dijalankan sekali pada file normal
    
    $tempPath = $path . '.temp';
    
    // 1. Baca file normal
    // 2. Enkripsi ke file .temp
    encryptFileStorage($path, $tempPath);
    
    // 3. Timpa file asli dengan yang terenkripsi
    if (copy($tempPath, $path)) {
        unlink($tempPath);
        echo "File $file berhasil dienkripsi.<br>";
    } else {
        echo "Gagal mengenkripsi $file.<br>";
    }
}
echo "<br>Selesai. Silakan hapus file ini.";
?>
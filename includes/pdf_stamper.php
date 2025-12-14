<?php
// FILE: includes/pdf_stamper.php

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../libs/fpdi/autoload.php';

use setasign\Fpdi\Fpdi;

// Update parameter: tambahkan $nomorSurat = null
function stampQrToPdf($sourceFile, $outputFile, $qrContent, $nomorSurat = null) {
    if (!file_exists($sourceFile)) {
        return false; 
    }

    // 1. Generate QR Code (sama seperti sebelumnya)
    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrContent);
    $tempQr = sys_get_temp_dir() . '/temp_qr_' . uniqid() . '.png';
    $qrImageContent = @file_get_contents($qrApiUrl);
    
    if (!$qrImageContent) return false;
    file_put_contents($tempQr, $qrImageContent);

    // 2. Mulai Proses PDF
    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false); 
    
    $pageCount = $pdf->setSourceFile($sourceFile);

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $templateId = $pdf->importPage($pageNo);
        $size = $pdf->getTemplateSize($templateId);
        
        // Orientasi halaman
        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
        $pdf->AddPage($orientation, array($size['width'], $size['height']));
        $pdf->useTemplate($templateId);

        // --- Halaman Pertama: Tulis Nomor Surat & QR ---
        if ($pageNo == 1) { 
            
            // A. TULIS NOMOR SURAT OTOMATIS
            if ($nomorSurat) {
                $pdf->SetFont('Arial', 'B', 11); // Font Tebal ukuran 11
                $pdf->SetTextColor(0, 0, 0);     // Warna Hitam
                
                // KOORDINAT PENEMPATAN NOMOR (Sesuaikan dengan Template Anda)
                // Contoh: X=35mm, Y=42mm (Biasanya posisi di bawah Kop Surat)
                // Anda mungkin perlu mencoba-coba angka ini agar pas di titik-titik template
                $pdf->SetXY(88, 45); 
                
                // Tulis Nomor (Background putih agar menimpa titik-titik template)
                $pdf->Cell(0, 0, $nomorSurat); 
                
                // Alternatif: Jika ingin menimpa dengan kotak putih dulu
                // $pdf->SetFillColor(255, 255, 255);
                // $pdf->Rect(35, 39, 80, 5, 'F'); // Buat kotak putih
                // $pdf->Text(35, 42, $nomorSurat); // Tulis teks di atasnya
            }

            // B. TEMPEL QR CODE (Di pojok kiri bawah)
            $xQr = 20; 
            $yQr = $size['height'] - 45; 
            $qrSize = 25;

            $pdf->Image($tempQr, $xQr, $yQr, $qrSize, $qrSize);
            
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetXY($xQr, $yQr + $qrSize + 2);
            $pdf->Cell($qrSize, 4, 'Digitally Signed', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 7);
            $pdf->SetX($xQr);
            $pdf->Cell($qrSize, 3, 'Scan to Verify', 0, 0, 'C');
        }
    }

    $pdf->Output('F', $outputFile);
    
    if (file_exists($tempQr)) unlink($tempQr);
    
    return true;
}
?>
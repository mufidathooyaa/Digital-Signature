<?php
session_start();

// 1. Generate kode acak (5 karakter)
$random_alpha = md5(rand());
$captcha_code = substr($random_alpha, 0, 5);

// 2. Simpan kode di session untuk verifikasi nanti
$_SESSION['captcha_code'] = $captcha_code;

// 3. Buat gambar
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 4. Warna-warna
$background_color = imagecolorallocate($image, 255, 255, 255); // Putih
$text_color = imagecolorallocate($image, 13, 59, 46);           // Hijau gelap (sesuai tema)
$line_color = imagecolorallocate($image, 64, 64, 64);           // Abu-abu untuk noise

imagefilledrectangle($image, 0, 0, $width, $height, $background_color);

// 5. Tambahkan garis-garis gangguan (noise) agar tidak mudah dibaca bot
for($i=0; $i<5; $i++) {
    imageline($image, 0, rand()%$height, 200, rand()%$height, $line_color);
}

// 6. Tambahkan titik-titik gangguan
for($i=0; $i<50; $i++) {
    imagesetpixel($image, rand()%$width, rand()%$height, $line_color);
}

// 7. Tulis teks CAPTCHA ke gambar
imagestring($image, 5, 35, 12, $captcha_code, $text_color);

// 8. Output gambar
header("Content-type: image/png");
imagepng($image);
imagedestroy($image);
?>
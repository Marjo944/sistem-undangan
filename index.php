<?php
/**
 * INDEX.PHP
 * Titik masuk utama sistem. File ini SENGAJA dibuat kosong dari tampilan —
 * tugasnya hanya satu: begitu server dijalankan dan browser membuka alamat
 * root ("/" atau "/index.php"), pengguna langsung diarahkan ke
    * beranda_publik.php — halaman informasi yang bisa dilihat siapa saja TANPA
  * login (bukan langsung ke beranda.php, yang mewajibkan login admin).
 *
 * Ini memastikan "index" selalu menjadi pintu masuk pertama, baik saat
 * dijalankan lewat XAMPP/Laragon (http://localhost/) maupun lewat skrip
 * jalankan_sistem (http://localhost:8000/).
 */
header('Location: beranda_publik.php');
exit;

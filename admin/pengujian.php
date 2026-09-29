<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin(['admin']);

$halaman_admin_aktif = 'pengujian';
$judul_halaman_admin = 'Pengujian';
$subjudul_halaman_admin = 'Menu internal admin untuk evaluasi sistem dan penelitian';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pengujian - Admin Rotama Jaya</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    .menu-pengujian-grid { display:grid; grid-template-columns:repeat(2,minmax(260px,1fr)); gap:20px; margin-top:18px; }
    .kartu-menu { background:#fff; border:1px solid var(--warna-batas); border-radius:14px; padding:24px; box-shadow:0 10px 30px rgba(15,23,42,0.04); }
    .kartu-menu h3 { margin:0 0 10px; font-size:20px; }
    .kartu-menu p { margin:0 0 16px; color:var(--warna-teks-sekunder); line-height:1.5; }
    .kartu-menu a { display:inline-block; text-decoration:none; }
    @media (max-width: 760px) { .menu-pengujian-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="tata-letak-admin">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>
  <div class="konten-admin">
    <?php include __DIR__ . '/../includes/topbar_admin.php'; ?>
    <div class="isi-admin">
      <div class="kartu-melayang" style="padding:22px 24px; margin-bottom:18px;">
        <h2 style="margin:0;">Internal Admin — Pengujian Sistem</h2>
        <p style="margin:8px 0 0; color:var(--warna-teks-sekunder);">
          Menu ini khusus untuk evaluasi internal admin dan kebutuhan penelitian sistem. Hanya admin yang dapat mengakses halaman ini.
        </p>
      </div>

      <div class="menu-pengujian-grid">
        <div class="kartu-menu">
          <h3>Pengujian CNN</h3>
          <p>Jalankan 30 skenario evaluasi pada metode CNN MobileNetV2, lihat tabel hasil, rata-rata, dan grafik performa.</p>
          <a href="<?= BASE_URL ?>/pengujian/uji_cnn.php" class="tombol-utama">Buka Pengujian CNN</a>
        </div>

        <div class="kartu-menu">
          <h3>Pengujian TF-IDF</h3>
          <p>Jalankan 30 skenario evaluasi pada metode TF-IDF + Kesamaan Kosinus, lihat tabel hasil, rata-rata, dan grafik performa.</p>
          <a href="<?= BASE_URL ?>/pengujian/uji_tfidf.php" class="tombol-utama">Buka Pengujian TF-IDF</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

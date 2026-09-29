<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
$halaman_aktif = 'tentang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tentang Kami - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  .isi-halaman { max-width: 800px; margin: 0 auto; padding: 60px 40px; }
  .isi-halaman p { color: var(--warna-teks-sekunder); line-height: 1.7; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navigasi.php'; ?>

<div class="isi-halaman">
  <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Tentang Kami</div>
  <h1>Percetakan Rotama Jaya</h1>
  <p>
    Rotama Jaya adalah percetakan undangan yang berdedikasi menghadirkan desain undangan
    berkualitas untuk berbagai momen penting: pernikahan, khitanan, ulang tahun, syukuran,
    hingga acara korporat.
  </p>
  <p>
    Melalui Sistem Rekomendasi Desain Undangan ini, kami membantu pelanggan menemukan
    desain yang paling sesuai dengan selera mereka — baik lewat pencarian kata kunci
    maupun dengan mengunggah gambar referensi. Sistem ini menggunakan kombinasi
    <strong>CNN MobileNetV2</strong> untuk pencarian berbasis gambar dan
    <strong>TF-IDF + Kesamaan Kosinus</strong> untuk pencarian berbasis kata kunci,
    yang berjalan secara terpisah (non-hybrid).
  </p>
  <p>
    Untuk pemesanan cetak, konsultasi desain, dan pembayaran, silakan hubungi tim kami
    langsung — proses ini masih dilakukan secara manual di luar sistem.
  </p>
</div>
</body>
</html>

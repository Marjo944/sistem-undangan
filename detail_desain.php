<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
$halaman_aktif = 'katalog';

$id_desain = (int) ($_GET['id'] ?? 0);
$stmt = $koneksi->prepare(
    "SELECT d.id_desain, d.tema, d.judul_desain, d.deskripsi_desain, d.nama_file_gambar, d.nama_file_webp,
            k.nama_kategori
     FROM desain_undangan d
     LEFT JOIN kategori_desain k ON k.id_kategori = d.id_kategori
     WHERE d.id_desain = ?"
);
$stmt->execute([$id_desain]);
$desain = $stmt->fetch();

if (!$desain) {
    header('Location: ' . BASE_URL . '/katalog.php');
    exit;
}

$skor_kemiripan = isset($_GET['skor']) ? (float) $_GET['skor'] : null;

$pesan = ''; $jenis_pesan = '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($desain['judul_desain']) ?> - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  .isi-detail { max-width: 1000px; margin: 0 auto; padding: 40px 24px 60px; }
  .grid-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start; }
  @media (max-width: 800px) { .grid-detail { grid-template-columns: 1fr; } }
  .galeri-utama img { width: 100%; border-radius: var(--radius-lembut); box-shadow: var(--bayangan-sedang); }
  .lencana-tema { display:inline-block; background: var(--warna-latar-alt); color: var(--warna-teks-sekunder);
    padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; margin-right: 6px; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navigasi.php'; ?>

<div class="isi-detail">
  <a href="<?= BASE_URL ?>/katalog.php" style="font-size:13px;color:var(--warna-teks-sekunder);text-decoration:none;">&larr; Kembali ke Katalog</a>

  <?php if ($pesan): ?>
    <div class="pesan-admin <?= $jenis_pesan ?>" style="margin-top:16px;"><?= htmlspecialchars($pesan) ?></div>
  <?php endif; ?>

  <div class="grid-detail" style="margin-top:20px;">
    <div class="galeri-utama">
      <img src="<?= htmlspecialchars(url_gambar_desain($desain['nama_file_gambar'], $desain['nama_file_webp'] ?? null)) ?>" alt="<?= htmlspecialchars($desain['judul_desain']) ?>" decoding="async">
    </div>

    <div>
      <?php if ($desain['nama_kategori']): ?><span class="lencana-tema"><?= htmlspecialchars($desain['nama_kategori']) ?></span><?php endif; ?>
      <?php if ($desain['tema']): ?><span class="lencana-tema"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $desain['tema']))) ?></span><?php endif; ?>

      <h1 style="margin:14px 0 6px;"><?= htmlspecialchars($desain['judul_desain']) ?></h1>

      <?php if ($skor_kemiripan !== null): ?>
        <div class="skor-kemiripan" style="position:static;display:inline-flex;margin-bottom:10px;">
          <span class="titik-hijau">✓</span>
          Skor kemiripan <?= number_format($skor_kemiripan * 100, 1) ?>% dengan referensimu
        </div>
      <?php endif; ?>

      <p style="color:var(--warna-teks-sekunder);line-height:1.6;"><?= nl2br(htmlspecialchars($desain['deskripsi_desain'] ?: 'Tidak ada deskripsi tambahan untuk desain ini.')) ?></p>

      <div style="margin-top:18px;background:#fff;border:1px solid var(--warna-batas);border-radius:var(--radius-lembut);padding:20px;">
        <p style="font-size:15px;font-weight:600;color:var(--warna-utama);margin:0 0 8px;">📞 Tertarik dengan desain ini?</p>
        <p style="font-size:14px;color:var(--warna-teks-sekunder);line-height:1.6;margin:0;">
          Hubungi tim Rotama Jaya secara langsung untuk informasi harga, pemesanan, dan konsultasi desain.
          Pesanan diproses melalui admin kami.
        </p>
      </div>
    </div>
  </div>
</div>
</body>
</html>

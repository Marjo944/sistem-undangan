<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
$halaman_aktif = 'katalog';

$id_kategori_filter = isset($_GET['kategori']) ? (int) $_GET['kategori'] : 0;
$daftar_kategori = $koneksi->query(
    "SELECT id_kategori, nama_kategori FROM kategori_desain ORDER BY nama_kategori"
)->fetchAll();
$kolom_katalog = 'id_desain, id_kategori, judul_desain, deskripsi_desain, nama_file_gambar, nama_file_webp';
$per_halaman = 24;
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));

if ($id_kategori_filter) {
    $hitung = $koneksi->prepare("SELECT COUNT(*) FROM desain_undangan WHERE id_kategori = ?");
    $hitung->execute([$id_kategori_filter]);
    $total_desain = (int) $hitung->fetchColumn();
} else {
    $total_desain = (int) $koneksi->query("SELECT COUNT(*) FROM desain_undangan")->fetchColumn();
}
$total_halaman = max(1, (int) ceil($total_desain / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

if ($id_kategori_filter) {
    $pernyataan = $koneksi->prepare(
        "SELECT $kolom_katalog FROM desain_undangan
         WHERE id_kategori = ? ORDER BY dibuat_pada DESC LIMIT $per_halaman OFFSET $offset"
    );
    $pernyataan->execute([$id_kategori_filter]);
} else {
    $pernyataan = $koneksi->query(
        "SELECT $kolom_katalog FROM desain_undangan
         ORDER BY dibuat_pada DESC LIMIT $per_halaman OFFSET $offset"
    );
}
$daftar_desain = $pernyataan->fetchAll();

$url_halaman_katalog = static function (int $nomor) use ($id_kategori_filter): string {
    $parameter = ['halaman' => $nomor];
    if ($id_kategori_filter) {
        $parameter['kategori'] = $id_kategori_filter;
    }
    return BASE_URL . '/katalog.php?' . http_build_query($parameter);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Katalog Desain - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  .isi-halaman { max-width: 1200px; margin: 0 auto; padding: 40px; }
  .grid-katalog { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 22px; margin-top: 24px; }
  .kartu-katalog img { width: 100%; height: 180px; object-fit: cover; display: block; }
  .kartu-katalog .info { padding: 14px; }
  .kartu-katalog[data-tilt-card] { transform-style: preserve-3d; will-change: transform; }
  .kartu-katalog[data-tilt-card] img { transform: translateZ(16px); }
  .daftar-pil-kategori { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
  .paginasi-katalog { display:flex; align-items:center; justify-content:center; gap:14px; margin-top:28px; }
  .paginasi-katalog a, .paginasi-katalog span { font-size:13px; }
  .paginasi-katalog a { color:var(--warna-utama); text-decoration:none; font-weight:700; }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navigasi.php'; ?>

<div class="isi-halaman">
  <h1>Katalog Desain Undangan</h1>
  <p style="color:var(--warna-teks-sekunder);">Jelajahi seluruh koleksi desain undangan Rotama Jaya.</p>

  <div class="daftar-pil-kategori">
    <a class="pil-kategori <?= !$id_kategori_filter ? 'aktif' : '' ?>" href="<?= BASE_URL ?>/katalog.php">Semua</a>
    <?php foreach ($daftar_kategori as $k): ?>
      <a class="pil-kategori <?= $id_kategori_filter === (int)$k['id_kategori'] ? 'aktif' : '' ?>"
         href="<?= BASE_URL ?>/katalog.php?kategori=<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="grid-katalog">
    <?php if ($daftar_desain): foreach ($daftar_desain as $d): ?>
      <a href="<?= BASE_URL ?>/detail_desain.php?id=<?= $d['id_desain'] ?>" class="kartu-melayang kartu-katalog" data-tilt-card style="text-decoration:none;color:inherit;display:block;">
        <img src="<?= htmlspecialchars(url_gambar_desain($d['nama_file_gambar'], $d['nama_file_webp'] ?? null)) ?>" alt="<?= htmlspecialchars($d['judul_desain']) ?>" loading="lazy" decoding="async">
        <div class="info">
          <h4 style="margin:0 0 6px;"><?= htmlspecialchars($d['judul_desain']) ?></h4>
          <p style="color:var(--warna-teks-sekunder);font-size:13px;margin:0;"><?= htmlspecialchars($d['deskripsi_desain']) ?></p>
        </div>
      </a>
    <?php endforeach; else: ?>
      <p style="color:var(--warna-teks-sekunder);">Belum ada desain pada kategori ini.</p>
    <?php endif; ?>
  </div>

  <?php if ($total_halaman > 1): ?>
    <nav class="paginasi-katalog" aria-label="Navigasi halaman katalog">
      <?php if ($halaman > 1): ?>
        <a href="<?= htmlspecialchars($url_halaman_katalog($halaman - 1)) ?>">&larr; Sebelumnya</a>
      <?php endif; ?>
      <span>Halaman <?= $halaman ?> dari <?= $total_halaman ?></span>
      <?php if ($halaman < $total_halaman): ?>
        <a href="<?= htmlspecialchars($url_halaman_katalog($halaman + 1)) ?>">Berikutnya &rarr;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
<script src="<?= htmlspecialchars(url_aset('assets/js/ruang_3d.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>

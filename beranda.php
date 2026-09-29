<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
$halaman_aktif = 'beranda';

$daftar_kategori = $koneksi->query(
    "SELECT id_kategori, nama_kategori FROM kategori_desain ORDER BY nama_kategori"
)->fetchAll();
$desain_terbaru = $koneksi->query(
    "SELECT id_desain, judul_desain, nama_file_gambar, nama_file_webp FROM desain_undangan ORDER BY dibuat_pada DESC LIMIT 6"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beranda - Sistem Rekomendasi Desain Undangan Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  .bagian-hero {
    position: relative; overflow: hidden;
    display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 40px; align-items: center;
    max-width: 1200px; margin: 0 auto; padding: 60px 40px 40px;
    perspective: 1400px;
  }
  .bola-hero-1 { width: 300px; height: 300px; background: var(--warna-utama); top: -60px; left: 30%; animation: melayang-lambat 8s ease-in-out infinite; }
  .bola-hero-2 { width: 220px; height: 220px; background: var(--warna-aksen-emas); bottom: -40px; right: 10%; animation: melayang-lambat 10s ease-in-out infinite reverse; }
  @keyframes melayang-lambat { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-22px); } }
  @keyframes putar-melayang { 0%,100% { transform: translateY(0) rotate(var(--sudut-dasar,0deg)); } 50% { transform: translateY(-16px) rotate(calc(var(--sudut-dasar,0deg) + 3deg)); } }

  .label-kecil { color: var(--warna-aksen-emas); font-weight: 700; font-size: 13px; letter-spacing: 1px; text-transform: uppercase; }
  .judul-hero { font-size: 38px; line-height: 1.25; margin: 10px 0 16px; }
  .judul-hero .sorot { color: var(--warna-aksen-emas); }
  .deskripsi-hero { color: var(--warna-teks-sekunder); font-size: 15px; line-height: 1.6; max-width: 480px; }

  .kotak-pencarian {
    position: relative; z-index: 1; background: #fff; border-radius: 20px;
    box-shadow: var(--bayangan-sedang); border: 1px solid var(--warna-batas);
    padding: 10px; margin-top: 28px; max-width: 480px;
  }
  .tab-pencarian { display: flex; gap: 6px; margin-bottom: 10px; }
  .tombol-tab {
    flex: 1; border: none; background: transparent; padding: 10px; border-radius: 12px;
    font-weight: 600; font-size: 13px; color: var(--warna-teks-sekunder); cursor: pointer;
  }
  .tombol-tab.aktif { background: var(--warna-utama); color: #fff; }
  .baris-input { display: flex; gap: 8px; }
  .baris-input input[type=text] {
    flex: 1; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--warna-batas); font-size: 14px;
  }
  .baris-input input[type=file] {
    flex: 1; padding: 8px; border-radius: 12px; border: 1px dashed var(--warna-batas); font-size: 13px;
  }
  .form-pencarian { display: none; }
  .form-pencarian.tampil { display: block; }

  .daftar-pil-kategori { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; position: relative; z-index: 1; }

  .panggung-kartu { position: relative; height: 420px; }
  .panggung-3d {
    position: relative; height: 420px; min-height: 320px; overflow: hidden;
    border: 1px solid rgba(201,150,47,.35); border-radius: 24px;
    background: linear-gradient(145deg, #fffdf8 0%, #f5e7d3 100%);
    box-shadow: var(--bayangan-sedang); transform-style: preserve-3d;
  }
  .panggung-3d canvas { display:block; width:100%; height:100%; }
  .label-3d {
    position:absolute; left:18px; bottom:16px; z-index:2; pointer-events:none;
    padding:8px 12px; border:1px solid rgba(107,30,43,.16); border-radius:999px;
    background:rgba(255,255,255,.74); color:var(--warna-utama); font-size:12px; font-weight:700;
    -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
  }
  .kartu-mengambang {
    position: absolute; width: 190px; border-radius: 18px; overflow: hidden;
    box-shadow: 0 20px 40px rgba(74,20,32,0.25);
    transform-style: preserve-3d;
    animation: putar-melayang 7s ease-in-out infinite;
  }
  .kartu-mengambang img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .kartu-1 { top: 10px; left: 40px; --sudut-dasar: -10deg; transform: rotate(-10deg); z-index: 1; }
  .kartu-2 { top: 60px; left: 130px; --sudut-dasar: 4deg; transform: rotate(4deg); z-index: 2; animation-delay: .8s; }
  .kartu-3 { top: 30px; left: 220px; --sudut-dasar: 14deg; transform: rotate(14deg); z-index: 3; animation-delay: 1.6s; }

  .bagian-katalog { max-width: 1200px; margin: 30px auto 70px; padding: 0 40px; }
  .judul-bagian { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .grid-katalog { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 22px; }
  .kartu-katalog { overflow: hidden; }
  .kartu-katalog img { width: 100%; height: 180px; object-fit: cover; display: block; }
  .kartu-katalog .info { padding: 14px; }

  @media (max-width: 860px) {
    .bagian-hero { grid-template-columns: 1fr; }
    .panggung-kartu { height: 320px; }
  }
</style>
</head>
<body>

<?php include __DIR__ . '/includes/navigasi.php'; ?>

<section class="bagian-hero">
  <div class="bentuk-geometris-samar bola-hero-1"></div>
  <div class="bentuk-geometris-samar bola-hero-2"></div>

  <div style="position:relative;z-index:1;">
    <div class="label-kecil">— Sistem Rekomendasi Desain</div>
    <h1 class="judul-hero">Temukan desain undangan yang <span class="sorot">berkesan</span> untuk momenmu</h1>
    <p class="deskripsi-hero">
      Cukup ketik kata kunci atau unggah gambar referensi — sistem kami akan mencocokkan
      dari ratusan desain pernikahan, ulang tahun, khitanan, dan syukuran koleksi Rotama Jaya.
    </p>

    <div class="kotak-pencarian">
      <div class="tab-pencarian">
        <button type="button" class="tombol-tab aktif" onclick="gantiTab('teks')">🔤 Cari dengan Kata Kunci</button>
        <button type="button" class="tombol-tab" onclick="gantiTab('gambar')">🖼️ Unggah Gambar Referensi</button>
      </div>

      <form id="formTeks" class="form-pencarian tampil" action="<?= BASE_URL ?>/cari_teks.php" method="POST">
        <div class="baris-input">
          <input type="text" name="kata_kunci" placeholder="Contoh: undangan pernikahan elegan emas..." required>
          <button type="submit" class="tombol-emas">Cari</button>
        </div>
      </form>

      <form id="formGambar" class="form-pencarian" action="<?= BASE_URL ?>/cari_gambar.php" method="POST" enctype="multipart/form-data">
        <div class="baris-input">
          <input type="file" name="gambar_uji" accept="image/*" required>
          <button type="submit" class="tombol-emas">Cari</button>
        </div>
      </form>


    </div>

    <div class="daftar-pil-kategori">
      <?php foreach ($daftar_kategori as $k): ?>
        <a class="pil-kategori" href="<?= BASE_URL ?>/katalog.php?kategori=<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panggung-kartu">
    <div class="panggung-3d" data-three-showcase data-three-images='["<?= BASE_URL ?>/assets/img/contoh_desain_1.jpg","<?= BASE_URL ?>/assets/img/contoh_desain_2.jpg","<?= BASE_URL ?>/assets/img/contoh_desain_3.jpg"]'>
      <span class="label-3d">Eksplorasi koleksi 3D</span>
    </div>
    <noscript>
    <div class="kartu-mengambang kartu-1"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_1.jpg" alt="Contoh desain undangan pernikahan" decoding="async"></div>
    <div class="kartu-mengambang kartu-2"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_2.jpg" alt="Contoh desain undangan ulang tahun" decoding="async"></div>
    <div class="kartu-mengambang kartu-3"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_3.jpg" alt="Contoh desain undangan syukuran" decoding="async"></div>
    </noscript>
  </div>
</section>

<section class="bagian-katalog">
  <div class="judul-bagian">
    <h2>Desain Terbaru</h2>
    <a href="<?= BASE_URL ?>/katalog.php" class="tombol-garis">Lihat Semua Katalog</a>
  </div>

  <div class="grid-katalog">
    <?php if ($desain_terbaru): foreach ($desain_terbaru as $d): ?>
      <div class="kartu-melayang kartu-katalog">
        <img src="<?= htmlspecialchars(url_gambar_desain($d['nama_file_gambar'], $d['nama_file_webp'] ?? null)) ?>" alt="<?= htmlspecialchars($d['judul_desain']) ?>" loading="lazy" decoding="async">
        <div class="info">
          <h4 style="margin:0 0 4px;"><?= htmlspecialchars($d['judul_desain']) ?></h4>
        </div>
      </div>
    <?php endforeach; else: ?>
      <p style="color:var(--warna-teks-sekunder);">Belum ada desain yang diunggah admin.</p>
    <?php endif; ?>
  </div>
</section>

<script src="<?= htmlspecialchars(url_aset('assets/js/pencarian.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.161.0/build/three.min.js" defer></script>
<script src="<?= htmlspecialchars(url_aset('assets/js/ruang_3d.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script>
function gantiTab(jenis) {
  document.getElementById('formTeks').classList.toggle('tampil', jenis === 'teks');
  document.getElementById('formGambar').classList.toggle('tampil', jenis === 'gambar');
  document.querySelectorAll('.tombol-tab').forEach((tombol, i) => {
    tombol.classList.toggle('aktif', (jenis === 'teks' && i === 0) || (jenis === 'gambar' && i === 1));
  });
}
</script>
</body>
</html>

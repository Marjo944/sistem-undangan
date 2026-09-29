<?php
/**
 * BERANDA_PUBLIK.PHP
 * Halaman muka publik — bisa dilihat SIAPA SAJA tanpa login.
 * Ini pintu masuk pertama pengunjung website (lihat index.php): berisi
 * perkenalan Percetakan Rotama Jaya, keunggulan, layanan, galeri contoh
 * desain, dan kontak.
 *
 * Sistem pencarian/rekomendasi (beranda.php) TETAP hanya bisa diakses lewat
  * login admin — sesuai ketentuan sistem bahwa pelanggan tidak
 * mengakses sistem pencarian secara langsung, melainkan didampingi admin
 * saat berkunjung ke toko. Halaman ini sengaja TIDAK memanggil
 * periksa_sesi_admin() supaya bisa dibuka tanpa login sama sekali.
 */
require_once __DIR__ . '/koneksi_database.php';

$sudah_masuk = isset($_SESSION['id_admin']);

$daftar_kategori_publik = tabel_ada($koneksi, 'kategori_desain')
    ? $koneksi->query("SELECT id_kategori, nama_kategori FROM kategori_desain ORDER BY nama_kategori")->fetchAll()
    : [];

$galeri_publik = tabel_ada($koneksi, 'desain_undangan')
    ? $koneksi->query("SELECT judul_desain, nama_file_gambar, nama_file_webp FROM desain_undangan ORDER BY dibuat_pada DESC LIMIT 8")->fetchAll()
    : [];

// Kalau data desain di database belum ada / belum diimpor admin, galeri tetap
// tampil dengan gambar contoh supaya halaman muka tidak pernah terlihat kosong.
$slide_galeri = [];
if (count($galeri_publik) >= 3) {
    foreach ($galeri_publik as $g) {
        $slide_galeri[] = [
            'gambar' => url_gambar_desain($g['nama_file_gambar'], $g['nama_file_webp'] ?? null),
            'judul'  => $g['judul_desain'],
        ];
    }
} else {
    $slide_galeri = [
        ['gambar' => BASE_URL . '/assets/img/contoh_desain_1.jpg', 'judul' => 'Undangan Pernikahan Elegan'],
        ['gambar' => BASE_URL . '/assets/img/contoh_desain_2.png', 'judul' => 'Undangan Syukuran Ceria'],
        ['gambar' => BASE_URL . '/assets/img/contoh_desain_3.jpg', 'judul' => 'Undangan Ulang Tahun Ceria'],
    ];
}

$daftar_layanan_bawaan = ['Pernikahan', 'Khitanan', 'Ulang Tahun', 'Syukuran', 'Korporat'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rotama Jaya — Percetakan Undangan &amp; Sistem Rekomendasi Desain</title>
<meta name="description" content="Percetakan Rotama Jaya: undangan pernikahan, khitanan, ulang tahun, dan syukuran, dibantu sistem rekomendasi desain berbasis AI.">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/ikon_192.png">
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  html { scroll-behavior: smooth; }
  section[id] { scroll-margin-top: 88px; }
  img { -webkit-user-drag: none; user-select: none; }

  /* ==== HERO ==== */
  .bagian-hero-publik {
    position: relative; overflow: hidden;
    display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 40px; align-items: center;
    max-width: 1200px; margin: 0 auto; padding: 64px 40px 70px;
  }
  .blob-publik-1 { width: 320px; height: 320px; background: var(--warna-utama); top: -80px; left: 28%; animation: melayang-blob-publik 9s ease-in-out infinite; }
  .blob-publik-2 { width: 240px; height: 240px; background: var(--warna-aksen-emas); bottom: -50px; right: 8%; animation: melayang-blob-publik 11s ease-in-out infinite reverse; }
  @keyframes melayang-blob-publik { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-26px); } }

  .isi-hero-publik { position: relative; z-index: 1; }
  .judul-hero-publik { font-size: 40px; line-height: 1.25; margin: 12px 0 16px; }
  .judul-hero-publik .sorot { color: var(--warna-aksen-emas); }
  .deskripsi-hero-publik { color: var(--warna-teks-sekunder); font-size: 15.5px; line-height: 1.7; max-width: 520px; }
  .tombol-cta-hero { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 28px; }

  /* Panggung 3D hero: mengikuti gerakan kursor (perspective di wadah luar,
     kemiringan di wadah dalam lewat custom property yang diisi JS) */
  .panggung-hero-3d { position: relative; z-index: 1; height: 420px; perspective: 1600px; }
  .dalam-panggung-3d {
    width: 100%; height: 100%; position: relative; transform-style: preserve-3d;
    transform: rotateX(var(--miring-x, 0deg)) rotateY(var(--miring-y, 0deg));
    transition: transform .2s ease-out;
  }
  .kartu-hero-3d {
    position: absolute; width: 200px; height: 260px; border-radius: 18px; overflow: hidden;
    box-shadow: 0 22px 44px rgba(74,20,32,0.28);
    animation: melayang-kartu-3d 7s ease-in-out infinite;
  }
  .kartu-hero-3d img { width: 100%; height: 100%; object-fit: cover; display: block; }
  @keyframes melayang-kartu-3d {
    0%,100% { transform: translateY(0) rotate(var(--sudut,0deg)) translateZ(var(--dalam,0px)); }
    50%     { transform: translateY(-20px) rotate(calc(var(--sudut,0deg) + 3deg)) translateZ(var(--dalam,0px)); }
  }
  .kartu-h1 { top: 4px;  left: 10px;  --sudut: -11deg; --dalam: 20px;  z-index: 1; }
  .kartu-h2 { top: 78px; left: 140px; --sudut: 5deg;   --dalam: 70px;  z-index: 3; animation-delay: .7s; }
  .kartu-h3 { top: 26px; left: 250px; --sudut: 14deg;  --dalam: 40px;  z-index: 2; animation-delay: 1.4s; }

  /* ==== UNGKAP SAAT DIGULIR (scroll reveal) ==== */
  .ungkap-gulir { opacity: 0; transform: translateY(46px) rotateX(6deg); transition: opacity .8s ease, transform .8s cubic-bezier(.16,1,.3,1); }
  .ungkap-gulir.tampil { opacity: 1; transform: translateY(0) rotateX(0deg); }

  /* ==== BAGIAN UMUM ==== */
  .bagian-umum { max-width: 1200px; margin: 0 auto; padding: 70px 40px; }
  .bagian-alt-luar { background: var(--warna-latar-alt); }
  .judul-bagian-tengah { text-align: center; max-width: 640px; margin: 0 auto 40px; }
  .judul-bagian-tengah h2 { margin: 8px 0 0; font-size: 28px; }

  /* ==== KENAPA KAMI ==== */
  .grid-fitur { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-top: 10px; }
  .kartu-fitur { padding: 30px 26px; text-align: left; }
  .ikon-fitur { font-size: 32px; margin-bottom: 14px; }
  .kartu-fitur h3 { margin: 0 0 8px; font-size: 17px; }
  .kartu-fitur p { margin: 0; color: var(--warna-teks-sekunder); font-size: 14px; line-height: 1.6; }

  /* ==== LAYANAN ==== */
  .baris-layanan { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
  .baris-layanan .pil-kategori { font-size: 14px; padding: 10px 22px; cursor: default; }

  /* ==== GALERI 3D (coverflow) ==== */
  .panggung-galeri { position: relative; height: 340px; perspective: 1400px; margin: 44px auto 14px; max-width: 900px; }
  .slide-galeri {
    position: absolute; top: 0; left: 50%; margin-left: -135px; width: 270px; height: 340px;
    border-radius: 18px; overflow: hidden; background: #fff; border: 1px solid var(--warna-batas);
    box-shadow: var(--bayangan-timbul);
    transition: transform .6s cubic-bezier(.16,1,.3,1), opacity .6s ease;
  }
  .slide-galeri img { width: 100%; height: 82%; object-fit: cover; display: block; }
  .keterangan-slide { padding: 10px 14px; font-size: 13px; font-weight: 700; color: var(--warna-utama); }
  .kontrol-galeri { display: flex; justify-content: center; gap: 16px; margin-top: 8px; }
  .kontrol-galeri button {
    width: 44px; height: 44px; border-radius: 50%; border: 1px solid var(--warna-batas); background: #fff;
    font-size: 20px; line-height: 1; cursor: pointer; color: var(--warna-utama);
    box-shadow: var(--bayangan-lembut); transition: var(--transisi-halus);
  }
  .kontrol-galeri button:hover { background: var(--warna-utama); color: #fff; }

  /* ==== TENTANG ==== */
  .grid-tentang { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 50px; align-items: center; }
  .grid-tentang p { color: var(--warna-teks-sekunder); line-height: 1.75; font-size: 14.5px; }
  .panggung-tentang-3d { position: relative; height: 260px; perspective: 1200px; }
  .kartu-tentang-3d { position: absolute; width: 170px; height: 210px; overflow: hidden; }
  .kartu-tentang-3d img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .kt-1 { top: 10px; left: 30px; transform: rotate(-8deg); z-index: 1; }
  .kt-2 { top: 50px; left: 150px; transform: rotate(9deg); z-index: 2; }

  /* ==== KONTAK ==== */
  .bagian-kontak { max-width: 1200px; margin: 0 auto; padding: 20px 40px 90px; }
  .kotak-kontak {
    max-width: 720px; margin: 0 auto; text-align: center; background: #fff; border-radius: 28px;
    padding: 52px 44px; box-shadow: var(--bayangan-timbul); border: 1px solid var(--warna-batas);
    position: relative; overflow: hidden;
  }
  .kotak-kontak h2 { margin: 0 0 12px; font-size: 25px; }
  .kotak-kontak p { color: var(--warna-teks-sekunder); line-height: 1.7; font-size: 14.5px; margin: 0 auto; max-width: 560px; }
  .info-kontak { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 26px; margin-top: 24px; color: var(--warna-teks-sekunder); font-size: 13.5px; }

  /* ==== FOOTER ==== */
  .footer-publik { text-align: center; padding: 34px 20px; border-top: 1px solid var(--warna-batas); color: var(--warna-teks-sekunder); font-size: 13px; }
  .footer-publik .merek-footer { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 10px; text-decoration: none; color: inherit; }
  .footer-publik .lambang-footer { height: 32px; width: 32px; border-radius: 50%; object-fit: cover; display: block; }
  .footer-publik b { color: var(--warna-utama); }

  @media (prefers-reduced-motion: reduce) {
    .blob-publik-1, .blob-publik-2, .kartu-hero-3d { animation: none !important; }
    .ungkap-gulir { transition: none; opacity: 1; transform: none; }
  }

  @media (max-width: 860px) {
    .bagian-hero-publik { grid-template-columns: 1fr; padding-top: 40px; }
    .panggung-hero-3d { height: 300px; }
    .grid-tentang { grid-template-columns: 1fr; }
    .panggung-tentang-3d { height: 220px; }
    .bagian-umum, .bagian-hero-publik, .bagian-kontak { padding-left: 20px; padding-right: 20px; }
  }
</style>
</head>
<body>

<nav class="bilah-navigasi">
  <a href="#atas" class="merek">
    <img src="<?= BASE_URL ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya" class="lambang">
    <span><b>Rotama Jaya</b> Design</span>
  </a>

  <ul class="menu-navigasi">
    <li><a href="#kenapa-kami">Kenapa Kami</a></li>
    <li><a href="#layanan">Layanan</a></li>
    <li><a href="#galeri">Galeri</a></li>
    <li><a href="#tentang">Tentang</a></li>
    <li><a href="#kontak">Kontak</a></li>
  </ul>

  <div>
    <?php if ($sudah_masuk): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="tombol-garis">📊 Dashboard</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/admin/masuk_admin.php" class="tombol-garis">🔐 Masuk Admin</a>
    <?php endif; ?>
  </div>
</nav>

<section id="atas" class="bagian-hero-publik">
  <div class="bentuk-geometris-samar blob-publik-1"></div>
  <div class="bentuk-geometris-samar blob-publik-2"></div>

  <div class="isi-hero-publik">
    <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Percetakan Undangan Rotama Jaya</div>
    <h1 class="judul-hero-publik">Cari desain undangan bukan dengan menebak-nebak, tapi dengan <span class="sorot">sistem yang mengerti selera Anda</span></h1>
    <p class="deskripsi-hero-publik">
      Kami melayani undangan pernikahan, khitanan, ulang tahun, dan syukuran. Saat Anda
      berkunjung ke toko, tim kami memakai sistem rekomendasi desain berbasis kecerdasan
      buatan — cukup ceritakan temanya atau tunjukkan gambar referensi, sistem akan
      mencarikan desain yang paling mendekati dari ratusan koleksi kami.
    </p>
    <div class="tombol-cta-hero">
      <a href="#galeri" class="tombol-emas">Lihat Galeri Desain</a>
      <a href="#kontak" class="tombol-garis">Hubungi Kami</a>
    </div>
  </div>

  <div class="panggung-hero-3d" id="panggungHero">
    <div class="dalam-panggung-3d" id="dalamPanggungHero">
      <div class="kartu-hero-3d kartu-h1"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_1.jpg" alt="Contoh desain undangan pernikahan" decoding="async"></div>
      <div class="kartu-hero-3d kartu-h2"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_2.png" alt="Contoh desain undangan syukuran" decoding="async"></div>
      <div class="kartu-hero-3d kartu-h3"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_3.jpg" alt="Contoh desain undangan ulang tahun" decoding="async"></div>
    </div>
  </div>
</section>

<section id="kenapa-kami" class="bagian-umum">
  <div class="judul-bagian-tengah ungkap-gulir">
    <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Kenapa Pilih Kami</div>
    <h2>Bukan sekadar cetak, tapi desain yang benar-benar pas</h2>
  </div>

  <div class="grid-fitur">
    <div class="kartu-melayang kartu-fitur ungkap-gulir">
      <div class="ikon-fitur">🎨</div>
      <h3>Ratusan Pilihan Desain</h3>
      <p>Koleksi desain terus diperbarui — dari gaya klasik dan elegan, sampai modern minimalis, untuk berbagai jenis acara.</p>
    </div>
    <div class="kartu-melayang kartu-fitur ungkap-gulir" style="transition-delay:.1s">
      <div class="ikon-fitur">🤖</div>
      <h3>Dibantu Sistem Pencarian Cerdas</h3>
      <p>Tim kami memakai sistem rekomendasi berbasis AI — pengenalan gambar maupun kata kunci — untuk menemukan desain yang paling sesuai selera Anda.</p>
    </div>
    <div class="kartu-melayang kartu-fitur ungkap-gulir" style="transition-delay:.2s">
      <div class="ikon-fitur">✅</div>
      <h3>Proses Custom &amp; Rapi</h3>
      <p>Setiap detail disesuaikan dengan kebutuhan acara Anda, dikerjakan tim berpengalaman hingga siap cetak.</p>
    </div>
  </div>
</section>

<section id="layanan" class="bagian-alt-luar">
  <div class="bagian-umum">
    <div class="judul-bagian-tengah ungkap-gulir">
      <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Layanan Kami</div>
      <h2>Desain undangan untuk setiap momen</h2>
    </div>
    <div class="baris-layanan ungkap-gulir">
      <?php if ($daftar_kategori_publik): foreach ($daftar_kategori_publik as $k): ?>
        <span class="pil-kategori"><?= htmlspecialchars($k['nama_kategori']) ?></span>
      <?php endforeach; else: foreach ($daftar_layanan_bawaan as $l): ?>
        <span class="pil-kategori"><?= htmlspecialchars($l) ?></span>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<section id="galeri" class="bagian-umum">
  <div class="judul-bagian-tengah ungkap-gulir">
    <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Galeri Desain</div>
    <h2>Sekilas koleksi desain undangan kami</h2>
  </div>

  <div class="panggung-galeri ungkap-gulir" id="panggungGaleri">
    <?php foreach ($slide_galeri as $i => $s): ?>
      <div class="slide-galeri" data-index="<?= $i ?>">
        <img src="<?= htmlspecialchars($s['gambar']) ?>" alt="<?= htmlspecialchars($s['judul']) ?>" loading="lazy" decoding="async">
        <div class="keterangan-slide"><?= htmlspecialchars($s['judul']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="kontrol-galeri">
    <button type="button" id="galeriSebelum" aria-label="Desain sebelumnya">‹</button>
    <button type="button" id="galeriBerikut" aria-label="Desain berikutnya">›</button>
  </div>
</section>

<section id="tentang" class="bagian-alt-luar">
  <div class="bagian-umum">
    <div class="grid-tentang">
      <div class="ungkap-gulir">
        <div class="label-kecil" style="color:var(--warna-aksen-emas);font-weight:700;letter-spacing:1px;text-transform:uppercase;font-size:13px;">— Tentang Kami</div>
        <h2 style="margin:8px 0 14px;">Percetakan Rotama Jaya</h2>
        <p>
          Rotama Jaya adalah percetakan undangan yang berdedikasi menghadirkan desain
          berkualitas untuk berbagai momen penting: pernikahan, khitanan, ulang tahun,
          syukuran.
        </p>
        <p>
          Saat Anda berkunjung, tim kami membantu mencarikan desain yang paling sesuai
          selera menggunakan sistem rekomendasi berbasis <strong>CNN MobileNetV2</strong>
          (pencarian dari gambar referensi) dan <strong>TF-IDF + Kesamaan Kosinus</strong>
          (pencarian dari kata kunci). Untuk pemesanan cetak dan konsultasi desain,
          silakan hubungi tim kami langsung.
        </p>
      </div>
      <div class="panggung-tentang-3d ungkap-gulir">
        <div class="kartu-melayang kartu-tentang-3d kt-1"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_2.png" alt="Contoh desain undangan" loading="lazy" decoding="async"></div>
        <div class="kartu-melayang kartu-tentang-3d kt-2"><img src="<?= BASE_URL ?>/assets/img/contoh_desain_3.jpg" alt="Contoh desain undangan" loading="lazy" decoding="async"></div>
      </div>
    </div>
  </div>
</section>

<section id="kontak" class="bagian-kontak">
  <div class="kotak-kontak ungkap-gulir">
    <div class="bentuk-geometris-samar" style="width:200px;height:200px;background:var(--warna-aksen-emas);top:-60px;right:-60px;"></div>
    <h2>Datang langsung, kami bantu carikan desainnya</h2>
    <p>
      Sistem pencarian desain kami hanya bisa diakses oleh tim di toko — jadi kunjungi
      kami, ceritakan acara Anda, dan biarkan sistem rekomendasi kami membantu menemukan
      desain yang paling pas.
    </p>
    <div class="info-kontak">
      <span>📍 Jl.Jamin Ginting No.77, Darat, Kec. Medan Baru, Kota Medan, Sumatera Utara 20222. No.081396191081, medan </span>
      <span>📞 081396191081</span>
      <span>🕒 Setiap hari, 08.00 – 17.00 WIB</span>
    </div>
    <?php if ($sudah_masuk): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="tombol-utama" style="margin-top:24px;display:inline-block;">Ke Dashboard</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/admin/masuk_admin.php" class="tombol-garis" style="margin-top:24px;display:inline-block;">Masuk sebagai Admin</a>
    <?php endif; ?>
  </div>
</section>

<footer class="footer-publik">
  <a href="#atas" class="merek-footer">
    <img src="<?= BASE_URL ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya" class="lambang-footer">
    <span><b>Rotama Jaya</b> Design</span>
  </a>
  <p>&copy; <?= date('Y') ?> Percetakan Rotama Jaya. Semua hak dilindungi.</p>
</footer>

<script>
(function () {
  // ---- 1) Hero 3D: kartu miring mengikuti gerakan kursor ----
  var panggung = document.getElementById('panggungHero');
  var dalam = document.getElementById('dalamPanggungHero');
  if (panggung && dalam && window.matchMedia('(hover: hover)').matches) {
    panggung.addEventListener('mousemove', function (e) {
      var rect = panggung.getBoundingClientRect();
      var x = (e.clientX - rect.left) / rect.width - 0.5;
      var y = (e.clientY - rect.top) / rect.height - 0.5;
      dalam.style.setProperty('--miring-x', (y * -12) + 'deg');
      dalam.style.setProperty('--miring-y', (x * 16) + 'deg');
    });
    panggung.addEventListener('mouseleave', function () {
      dalam.style.setProperty('--miring-x', '0deg');
      dalam.style.setProperty('--miring-y', '0deg');
    });
  }

  // ---- 2) Ungkap elemen saat digulir ke layar (scroll reveal) ----
  var elemenUngkap = document.querySelectorAll('.ungkap-gulir');
  if ('IntersectionObserver' in window) {
    var pengamat = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('tampil');
          pengamat.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    elemenUngkap.forEach(function (el) { pengamat.observe(el); });
  } else {
    elemenUngkap.forEach(function (el) { el.classList.add('tampil'); });
  }

  // ---- 3) Galeri 3D coverflow, berputar otomatis seperti video ----
  var slide = document.querySelectorAll('.slide-galeri');
  var indeksSekarang = 0;
  var jedaOtomatis;

  function tampilkanGaleri() {
    var total = slide.length;
    slide.forEach(function (s, i) {
      var jarak = i - indeksSekarang;
      if (jarak > total / 2) jarak -= total;
      if (jarak < -total / 2) jarak += total;
      var jarakMutlak = Math.abs(jarak);
      s.style.transform = 'translateX(' + (jarak * 230) + 'px) translateZ(' + (jarakMutlak * -160) + 'px) rotateY(' + (jarak * -32) + 'deg) scale(' + (1 - jarakMutlak * 0.14) + ')';
      s.style.zIndex = String(100 - jarakMutlak);
      s.style.opacity = jarakMutlak > 2 ? '0' : String(1 - jarakMutlak * 0.2);
    });
  }
  function galeriBerikut() { indeksSekarang = (indeksSekarang + 1) % slide.length; tampilkanGaleri(); }
  function galeriSebelum() { indeksSekarang = (indeksSekarang - 1 + slide.length) % slide.length; tampilkanGaleri(); }
  function mulaiOtomatis() { jedaOtomatis = setInterval(galeriBerikut, 3500); }

  if (slide.length) {
    tampilkanGaleri();
    mulaiOtomatis();
    var tombolBerikut = document.getElementById('galeriBerikut');
    var tombolSebelum = document.getElementById('galeriSebelum');
    [tombolBerikut, tombolSebelum].forEach(function (tombol) {
      tombol.addEventListener('click', function () {
        clearInterval(jedaOtomatis);
        this === tombolBerikut ? galeriBerikut() : galeriSebelum();
        mulaiOtomatis();
      });
    });
  }
})();
</script>

</body>
</html>

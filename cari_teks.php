<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
require_once __DIR__ . '/includes/rekomendasi_helper.php';


$pesan_kesalahan = '';
$hasil_kaya = [];
$jumlah_sebelum_filter = 0;

$jumlah_hasil_dipilih = isset($_GET['jumlah_hasil']) ? (int) $_GET['jumlah_hasil'] : (isset($_POST['jumlah_hasil']) ? (int) $_POST['jumlah_hasil'] : 10);
$jumlah_hasil_dipilih = in_array($jumlah_hasil_dipilih, [5, 10]) ? $jumlah_hasil_dipilih : 10;
$_SESSION['jumlah_hasil'] = $jumlah_hasil_dipilih;

$kata_kunci = trim($_POST['kata_kunci'] ?? $_GET['kata_kunci'] ?? '');
$ada_pencarian = $kata_kunci !== '';

if (!empty($_FILES['gambar_uji']['name'] ?? null) && $kata_kunci !== '') {
    $pesan_kesalahan = 'Silakan pilih salah satu jalur pencarian saja: gambar ATAU kata kunci, tidak keduanya.';
    $ada_pencarian = false;
} elseif ($ada_pencarian) {
    $data = panggil_layanan_teks($kata_kunci);

    if ($data === null) {
        $pesan_kesalahan = 'Layanan pencarian (Python) tidak dapat dihubungi. Pastikan aplikasi_layanan.py sedang berjalan di ' . ALAMAT_LAYANAN_PYTHON . '.';
        $ada_pencarian = false;
    } elseif (!empty($data['kesalahan'])) {
      $pesan_kesalahan = 'Pencarian TF-IDF gagal: ' . $data['kesalahan'];
      $ada_pencarian = false;
    } else {
        $hasil_kaya = perkaya_hasil_rekomendasi($koneksi, $data['hasil'] ?? []);
        $jumlah_sebelum_filter = count($hasil_kaya);

        // (Riwayat pencarian pelanggan tidak dicatat — sistem hanya dioperasikan admin)
    }
} else {
    $pesan_kesalahan = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Silakan masukkan kata kunci untuk memulai pencarian.' : '';
}

/* ===== Terapkan filter kategori/tema/skor minimum (Gambar 4.8) ===== */
$kategori_terpilih = array_map('strval', $_GET['kategori'] ?? []);
$tema_terpilih = $_GET['tema'] ?? [];
$skor_minimum_persen = isset($_GET['skor_min']) ? max(0, min(100, (int) $_GET['skor_min'])) : 0;

$hasil_tersaring = terapkan_filter_hasil($hasil_kaya, $kategori_terpilih, $tema_terpilih, $skor_minimum_persen / 100);

$daftar_kategori = $koneksi->query(
    "SELECT id_kategori, nama_kategori FROM kategori_desain ORDER BY nama_kategori"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cari Berdasarkan Kata Kunci - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php $halaman_aktif = ''; include __DIR__ . '/includes/navigasi.php'; ?>

<div style="max-width:1100px;margin:0 auto;padding:32px 24px 0;">
  <h1 style="color:var(--warna-utama);margin-bottom:6px;">Cari Berdasarkan Kata Kunci</h1>
  <p style="color:var(--warna-teks-sekunder);margin-top:0;">Masukkan kata kunci (misal: "undangan pernikahan elegan emas"), sistem akan mencari desain relevan menggunakan TF-IDF + Kesamaan Kosinus.</p>

  <?php if ($pesan_kesalahan): ?>
    <div style="background:#fef2f2;color:#b91c1c;padding:12px 16px;border-radius:10px;margin:16px 0;">
      <?= htmlspecialchars($pesan_kesalahan) ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="formTeks" style="margin:24px 0;display:flex;gap:12px;">
    <input type="hidden" name="jumlah_hasil" value="<?= $jumlah_hasil_dipilih ?>">
    <input type="text" name="kata_kunci" value="<?= htmlspecialchars($kata_kunci) ?>" placeholder="Contoh: undangan pernikahan elegan" required
      style="flex:1;padding:12px 16px;border-radius:10px;border:1px solid var(--warna-batas);">
    <button type="submit" class="tombol-utama">Cari</button>
  </form>
</div>

<script src="<?= htmlspecialchars(url_aset('assets/js/pencarian.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>

<?php
$jenis_jalur = 'teks';
$label_kueri = $kata_kunci;
$label_metode = 'TF-IDF';
include __DIR__ . '/includes/tampilan_hasil_rekomendasi.php';
?>
</body>
</html>

<?php
require_once __DIR__ . '/koneksi_database.php';
periksa_sesi_admin();
require_once __DIR__ . '/includes/rekomendasi_helper.php';


$pesan_kesalahan = '';
$hasil_kaya = [];
$jumlah_sebelum_filter = 0;
$ada_pencarian = false;
$label_kueri = '';

$jumlah_hasil_dipilih = isset($_GET['jumlah_hasil']) ? (int) $_GET['jumlah_hasil'] : ($_SESSION['jumlah_hasil'] ?? 10);
$jumlah_hasil_dipilih = in_array($jumlah_hasil_dipilih, [5, 10]) ? $jumlah_hasil_dipilih : 10;
$_SESSION['jumlah_hasil'] = $jumlah_hasil_dipilih;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Peraturan: jika gambar dan teks dikirim sekaligus, tolak dan minta pilih salah satu
    if (!empty($_FILES['gambar_uji']['name']) && !empty(trim($_POST['kata_kunci'] ?? ''))) {
        $pesan_kesalahan = 'Silakan pilih salah satu jalur pencarian saja: gambar ATAU kata kunci, tidak keduanya.';
    } elseif (!empty($_FILES['gambar_uji']['name'])) {
        // Baca ISI berkas untuk menentukan apakah ini gambar sungguhan dan format
        // apa (bukan menebak dari nama/ekstensi file) — dengan begini SEMUA format
        // gambar yang didukung PHP (JPG, PNG, GIF, BMP, WEBP, dan lainnya) tetap
        // bisa dibaca sistem, tapi berkas yang bukan gambar sama sekali tetap ditolak.
        $info_gambar = @getimagesize($_FILES['gambar_uji']['tmp_name']);
        $nama_asli = basename($_FILES['gambar_uji']['name']);

        if ($info_gambar === false) {
            $pesan_kesalahan = '⚠️ Berkas yang diunggah bukan gambar yang valid. Coba unggah file gambar lain.';
        } else {
            $ekstensi = image_type_to_extension($info_gambar[2], false) ?: 'jpg';
            $lokasi_sementara = sys_get_temp_dir() . '/' . uniqid('uji_') . '.' . $ekstensi;
            move_uploaded_file($_FILES['gambar_uji']['tmp_name'], $lokasi_sementara);

            $data = panggil_layanan_gambar($lokasi_sementara);
            @unlink($lokasi_sementara);

            if ($data === null) {
                $pesan_kesalahan = 'Layanan pencarian (Python) tidak dapat dihubungi. Pastikan aplikasi_layanan.py sedang berjalan di ' . ALAMAT_LAYANAN_PYTHON . '.';
            } else {
                $hasil_kaya = perkaya_hasil_rekomendasi($koneksi, $data['hasil'] ?? []);
                $ada_pencarian = true;
                $label_kueri = $nama_asli;

                // Simpan ke sesi supaya saat form filter (GET) dikirim ulang tanpa
                // mengunggah gambar lagi, hasil pencarian gambar ini tetap tersedia.
                $_SESSION['sesi_hasil_gambar'] = [
                    'label_kueri' => $label_kueri,
                    'hasil_kaya'  => $hasil_kaya,
                ];

                // (Riwayat pencarian pelanggan tidak dicatat — sistem hanya dioperasikan admin)
            }
        }
    } else {
        $pesan_kesalahan = 'Silakan unggah gambar untuk memulai pencarian.';
    }
} elseif (isset($_SESSION['sesi_hasil_gambar'])) {
    // Permintaan GET (mis. menerapkan filter pada panel kiri) — pakai hasil
    // pencarian gambar terakhir yang tersimpan di sesi, tanpa perlu unggah ulang.
    $ada_pencarian = true;
    $label_kueri = $_SESSION['sesi_hasil_gambar']['label_kueri'];
    $hasil_kaya = $_SESSION['sesi_hasil_gambar']['hasil_kaya'];
}

$jumlah_sebelum_filter = count($hasil_kaya);

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
<title>Cari Berdasarkan Gambar - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php $halaman_aktif = ''; include __DIR__ . '/includes/navigasi.php'; ?>

<div style="max-width:1100px;margin:0 auto;padding:32px 24px 0;">
  <h1 style="color:var(--warna-utama);margin-bottom:6px;">Cari Berdasarkan Gambar</h1>
  <p style="color:var(--warna-teks-sekunder);margin-top:0;">Unggah gambar desain undangan yang Anda sukai, sistem akan mencari desain paling serupa menggunakan CNN MobileNetV2.</p>

  <?php if ($pesan_kesalahan): ?>
    <div style="background:#fef2f2;color:#b91c1c;padding:12px 16px;border-radius:10px;margin:16px 0;">
      <?= htmlspecialchars($pesan_kesalahan) ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="formGambar" enctype="multipart/form-data" style="margin:24px 0;">
    <input type="hidden" name="jumlah_hasil" value="<?= $jumlah_hasil_dipilih ?>">
    <input type="file" name="gambar_uji" accept="image/*" required
      style="display:block;margin-bottom:16px;padding:12px;border:1px dashed var(--warna-batas);border-radius:10px;width:100%;">
    <button type="submit" class="tombol-utama">Cari Desain Serupa</button>
  </form>
</div>

<script src="<?= htmlspecialchars(url_aset('assets/js/pencarian.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>

<?php
$jenis_jalur = 'gambar';
$label_metode = 'CNN MobileNetV2';
include __DIR__ . '/includes/tampilan_hasil_rekomendasi.php';
?>
</body>
</html>

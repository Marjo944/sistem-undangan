<?php
require_once __DIR__ . '/../koneksi_database.php';
require_once __DIR__ . '/../includes/pengujian_helper.php';
periksa_sesi_admin(['admin']);

if (($_SESSION['peran'] ?? 'admin') !== 'admin') {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$pesan = '';
$jenis_pesan = 'sukses';
$layanan_python_aktif = null;

$daftar_kategori = $koneksi->query("SELECT * FROM kategori_desain ORDER BY nama_kategori")->fetchAll();
$daftar_tema = ['elegan', 'floral', 'modern', 'minimalis', 'islami', 'adat_batak'];

// Cek koneksi layanan Python hanya saat membuka halaman. POST langsung diproses
// oleh handler di bawah agar klik tombol tidak menunggu pemeriksaan tambahan.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
try {
    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/health');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 5000);
    $respon_health = curl_exec($curl);
    $kode_health = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $layanan_python_aktif = $respon_health !== false && $kode_health === 200;
} catch (Exception $e) {
    $layanan_python_aktif = false;
}
}

if ($layanan_python_aktif === false) {
    $pesan = 'Layanan Python tidak terhubungi. Pastikan aplikasi_layanan.py sedang berjalan di ' . ALAMAT_LAYANAN_PYTHON . '. Pengujian akan menggunakan mode fallback (PHP).';
    $jenis_pesan = 'gagal';
}

$total_desain = $koneksi->query("SELECT COUNT(*) FROM desain_undangan")->fetchColumn();
$desain_dengan_cnn = $koneksi->query("SELECT COUNT(*) FROM desain_undangan WHERE vektor_fitur_cnn IS NOT NULL AND vektor_fitur_cnn <> ''")->fetchColumn();
$desain_dengan_kategori_tema = $koneksi->query("SELECT COUNT(*) FROM desain_undangan WHERE id_kategori IS NOT NULL AND tema IS NOT NULL")->fetchColumn();

  // ---- Menjalankan pengujian CNN otomatis dari gambar referensi yang diunggah ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_uji_cnn'])) {
        $id_kategori_uji = (int) ($_POST['kategori_uji_cnn'] ?? 0);
        $tema_uji = $_POST['tema_uji_cnn'] ?? '';

        if (empty($_FILES['gambar_uji_cnn']['name'])) {
            $pesan = 'Unggah gambar uji terlebih dahulu.';
            $jenis_pesan = 'gagal';
        } elseif (!(function () use ($koneksi, $id_kategori_uji, $tema_uji): bool {
            $stmt = $koneksi->prepare('SELECT COUNT(*) FROM desain_undangan WHERE id_kategori = ? AND tema = ?');
            $stmt->execute([$id_kategori_uji, $tema_uji]);
            return (int) $stmt->fetchColumn() > 0;
        })()) {
            $pesan = 'Kombinasi kategori dan tema tersebut belum tersedia pada data desain. Pilih tema yang sesuai dengan katalog agar metrik pengujian dapat dihitung.';
            $jenis_pesan = 'gagal';
        } elseif ($_FILES['gambar_uji_cnn']['error'] !== UPLOAD_ERR_OK) {
            $pesan = 'Gagal mengunggah berkas. Kode kesalahan: ' . $_FILES['gambar_uji_cnn']['error'];
            $jenis_pesan = 'gagal';
        } else {
            $info_gambar = @getimagesize($_FILES['gambar_uji_cnn']['tmp_name']);
            $nama_file_uji = basename($_FILES['gambar_uji_cnn']['name']);

            if ($info_gambar === false) {
                $pesan = 'Berkas yang diunggah bukan gambar yang valid.';
                $jenis_pesan = 'gagal';
            } else {
                $ekstensi = image_type_to_extension($info_gambar[2], false) ?: 'jpg';
                $path_sementara = sys_get_temp_dir() . '/' . uniqid('uji_') . '.' . $ekstensi;
                if (!move_uploaded_file($_FILES['gambar_uji_cnn']['tmp_name'], $path_sementara)) {
                    $pesan = 'Gagal menyimpan berkas sementara.';
                    $jenis_pesan = 'gagal';
                } else {
                    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/cari_berdasarkan_gambar');
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, ['gambar' => new CURLFile($path_sementara)]);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                    $tanggapan = curl_exec($curl);
                    $kode_http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);
                    @unlink($path_sementara);

                    if ($tanggapan === false) {
                        $pesan = 'Layanan pencarian (Python) tidak dapat dihubungi. Pastikan aplikasi_layanan.py sedang berjalan.';
                        $jenis_pesan = 'gagal';
                    } elseif ($kode_http >= 400) {
                        $data_error = json_decode($tanggapan, true);
                        $pesan = 'Error dari layanan Python: ' . ($data_error['kesalahan'] ?? 'Kesalahan tidak diketahui');
                        $jenis_pesan = 'gagal';
                    } else {
                        $data = json_decode($tanggapan, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $pesan = 'Respons layanan Python tidak valid.';
                            $jenis_pesan = 'gagal';
                        } else {
                            $hasil = $data['hasil'] ?? [];
                            if (empty($hasil)) {
                                $pesan = 'Tidak ada hasil rekomendasi dari layanan Python.';
                                $jenis_pesan = 'gagal';
                            } else {
                                [$p5, $p10, $i5, $i10, $f5, $f10] = hitung_metrik_evaluasi($hasil, $id_kategori_uji, $tema_uji, $koneksi);
                                $no_skenario = nomor_skenario_berikutnya($koneksi, 'pengujian_cnn');
                                $stmt = $koneksi->prepare(
                                    "INSERT INTO pengujian_cnn
                                    (nomor_skenario, nama_gambar_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                                );
                                $stmt->execute([$no_skenario, $nama_file_uji, $p5, $p10, $i5, $i10, $f5, $f10]);
                                $pesan = "Skenario CNN #$no_skenario tersimpan otomatis — Presisi@5: " . number_format($p5, 2)
                                    . ", Presisi@10: " . number_format($p10, 2) . ", Ingatan@10: " . number_format($i10, 2)
                                    . ", Skor-F1@10: " . number_format($f10, 2) . ".";
                                $jenis_pesan = 'sukses';
                            }
                        }
                    }
                }
            }
        }
    }

// ---- Menjalankan SEMUA skenario CNN otomatis sekaligus, memakai desain yang sudah ada di katalog ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_uji_massal_cnn'])) {
    $hasil_cnn = jalankan_evaluasi_massal($koneksi, 'cnn');

    if (!$hasil_cnn) {
        $pesan = 'Belum ada desain dengan kategori & tema terisi untuk dijadikan bahan uji otomatis. Lengkapi data desain (kategori & tema) di menu Kelola Desain terlebih dahulu.';
        $jenis_pesan = 'gagal';
    } else {
        $jumlah = 0;
        foreach ($hasil_cnn as $baris) {
            $no = nomor_skenario_berikutnya($koneksi, 'pengujian_cnn');
            $koneksi->prepare(
                "INSERT INTO pengujian_cnn
                (nomor_skenario, nama_gambar_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$no, $baris['nama_gambar_uji'], $baris['presisi_5'], $baris['presisi_10'], $baris['ingatan_5'], $baris['ingatan_10'], $baris['skor_f1_5'], $baris['skor_f1_10']]);
            $jumlah++;
        }
        $pesan = "Pengujian massal CNN selesai — $jumlah skenario otomatis dijalankan & disimpan.";
        $jenis_pesan = 'sukses';
    }
}

if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['impor_pengujian_cnn']) && !empty($_FILES['berkas_cnn']['name'])) {
    [$diimpor, $dilewati] = impor_csv_pengujian($koneksi, 'cnn', $_FILES['berkas_cnn']);
    $pesan = "Impor CSV selesai — $diimpor baris diimpor" . ($dilewati ? ", $dilewati dilewati (kolom kosong)" : '') . ".";
    $jenis_pesan = 'sukses';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_semua_skenario_cnn'])) {
    $koneksi->prepare("DELETE FROM pengujian_cnn")->execute();
    $pesan = 'Semua skenario CNN berhasil dihapus.';
    $jenis_pesan = 'sukses';
}

$data_cnn = $koneksi->query(
    "SELECT nomor_skenario, nama_gambar_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10
     FROM pengujian_cnn ORDER BY nomor_skenario"
)->fetchAll();

$rerata_cnn = [
    'presisi_5' => rerata_metrik($data_cnn, 'presisi_5'), 'presisi_10' => rerata_metrik($data_cnn, 'presisi_10'),
    'ingatan_5' => rerata_metrik($data_cnn, 'ingatan_5'), 'ingatan_10' => rerata_metrik($data_cnn, 'ingatan_10'),
    'skor_f1_5' => rerata_metrik($data_cnn, 'skor_f1_5'), 'skor_f1_10' => rerata_metrik($data_cnn, 'skor_f1_10'),
];

$jumlah_skenario_cnn = count($data_cnn);
$target_skenario = 30;
$progres_persen = min(100, round(($jumlah_skenario_cnn / $target_skenario) * 100));

$halaman_admin_aktif = 'laporan_cnn';
$judul_halaman_admin = 'Laporan Pengujian CNN';
$subjudul_halaman_admin = 'Hasil pengujian metode CNN MobileNetV2 (Precision / Recall / F1-Score)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pengujian CNN - Admin Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
  .tabel-bungkus { overflow-x:auto; border-radius:12px; border:1px solid var(--warna-batas); margin:16px 0 32px; }
  table { width:100%; border-collapse:collapse; background:#fff; min-width:720px; }
  th, td { padding:10px 12px; text-align:center; border-bottom:1px solid var(--warna-batas); font-size:14px; white-space:nowrap; }
  th { background:var(--warna-latar-alt); color:var(--warna-teks-utama); position:sticky; top:0; font-size:13px; }
  tbody tr:nth-child(even) { background:#fafafa; }
  tbody tr:hover { background:#f1f5ff; }
  tr.baris-rerata td { font-weight:700; background:#eef2ff; border-top:2px solid var(--warna-utama); }
  section { margin-bottom:44px; }
  .tombol-hapus {
    background: #fff1f2; color: #b91c1c; border: 1px solid #fecdd3; padding: 7px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none;
  }
  .tombol-hapus:hover { background: #fee2e2; }
  .kartu-ringkasan { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
  @media (max-width: 900px) { .kartu-ringkasan { grid-template-columns:1fr 1fr; } }
  .kartu-mini { background:#fff; border:1px solid var(--warna-batas); border-radius:12px; padding:16px 18px; }
  .kartu-mini .label { font-size:12px; color:var(--warna-teks-sekunder); font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
  .kartu-mini .nilai { font-size:24px; font-weight:800; color:var(--warna-teks-utama); margin-top:4px; }
  .bar-progres { height:8px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:8px; }
  .bar-progres .isi { height:100%; background:#4F46E5; }
</style>
</head>
<body>
<div class="tata-letak-admin">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

  <div class="konten-admin">
    <?php include __DIR__ . '/../includes/topbar_admin.php'; ?>

    <div class="isi-admin">

  <?php if ($pesan): ?>
    <div class="pesan-admin <?= $jenis_pesan ?>"><?= htmlspecialchars($pesan) ?></div>
  <?php endif; ?>

  <div style="background:#f8fafc;border:1px solid var(--warna-batas);border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:var(--warna-teks-sekunder);">
    <strong>Diagnostik:</strong>
    Layanan Python: <?= $layanan_python_aktif === null ? '<span style="color:#a16207;">Diperiksa saat proses</span>' : ($layanan_python_aktif ? '<span style="color:#16a34a;">Terhubung</span>' : '<span style="color:#dc2626;">Tidak terhubung</span>') ?> |
    Total desain: <?= (int) $total_desain ?> |
    Dengan vektor CNN: <?= (int) $desain_dengan_cnn ?> |
    Dengan kategori & tema: <?= (int) $desain_dengan_kategori_tema ?>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:18px;">
    <a href="<?= BASE_URL ?>/pengujian/uji_tfidf.php" class="tombol-garis">↔ Lihat Laporan TF-IDF</a>
    <a href="<?= BASE_URL ?>/admin/ekspor_laporan.php?format=pdf&internal=1" target="_blank" rel="noopener" class="tombol-utama" style="text-decoration:none;">📑 Preview Hasil CNN/TF-IDF</a>
  </div>

  <div class="kartu-ringkasan">
    <div class="kartu-mini">
      <div class="label">Skenario Tercatat</div>
      <div class="nilai"><?= $jumlah_skenario_cnn ?> / <?= $target_skenario ?></div>
      <div class="bar-progres"><div class="isi" style="width:<?= $progres_persen ?>%;"></div></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Presisi@10</div>
      <div class="nilai"><?= number_format($rerata_cnn['presisi_10'], 3) ?></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Ingatan@10</div>
      <div class="nilai"><?= number_format($rerata_cnn['ingatan_10'], 3) ?></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Skor-F1@10</div>
      <div class="nilai"><?= number_format($rerata_cnn['skor_f1_10'], 3) ?></div>
    </div>
  </div>

  <div class="kartu-melayang" style="padding:24px;margin-bottom:24px;border:2px solid var(--warna-utama);">
    <h2 style="margin-top:0;color:var(--warna-teks-utama);">🚀 Jalankan Semua Skenario CNN Otomatis</h2>
    <p style="color:var(--warna-teks-sekunder);font-size:13px;margin-top:-6px;">
      Memakai gambar desain yang SUDAH ADA di katalog (dengan kategori & tema terisi) sebagai bahan
      uji — kategori/tema desain itu sendiri jadi target yang diharapkan. Boleh diklik berkali-kali
      kapan saja (misalnya setelah menambah desain baru); skenario baru ditambahkan, bukan menimpa yang lama.
    </p>
    <form method="POST" id="formMassalCnn">
      <input type="hidden" name="jalankan_uji_massal_cnn" value="1">
      <button type="submit" class="tombol-utama" id="tombolMassalCnn">🚀 Jalankan Semua Skenario CNN Sekarang</button>
    </form>

    <script>
    document.getElementById('formMassalCnn').addEventListener('submit', function() {
        var btn = document.getElementById('tombolMassalCnn');
        btn.disabled = true;
        btn.textContent = 'Memproses... Mohon tunggu.';
    });
    </script>
  </div>

  <div class="kartu-melayang" style="padding:24px;margin-bottom:32px;">
    <h2 style="margin-top:0;color:var(--warna-teks-utama);">Uji Skenario Tunggal (Opsional)</h2>
    <p style="color:var(--warna-teks-sekunder);margin-top:-8px;">
      Unggah gambar referensi pelanggan. Untuk evaluasi yang relevan, gunakan gambar desain
      yang kategorinya sudah diketahui lalu pilih kategori dan tema yang sesuai.
    </p>

    <form method="POST" action="<?= BASE_URL ?>/admin/laporan_pengujian_cnn.php" enctype="multipart/form-data" style="max-width:420px;border:1px solid var(--warna-batas);border-radius:12px;padding:18px;margin-top:16px;" id="formUjiCnn">
      <input type="hidden" name="jalankan_uji_cnn" value="1">
      <label>Gambar Uji</label>
      <input type="file" name="gambar_uji_cnn" accept="image/*" required style="width:100%;margin-bottom:12px;" id="gambarUjiCnn">
      <label>Kategori yang Diharapkan</label>
      <select name="kategori_uji_cnn" required style="width:100%;margin-bottom:12px;padding:8px;">
        <?php foreach ($daftar_kategori as $k): ?>
          <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Tema yang Diharapkan</label>
      <select name="tema_uji_cnn" required style="width:100%;margin-bottom:14px;padding:8px;">
        <?php foreach ($daftar_tema as $t): ?>
          <option value="<?= $t ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="tombol-utama" style="width:100%;" id="tombolUjiCnn">Jalankan Uji CNN</button>
    </form>

    <script>
    document.getElementById('formUjiCnn').addEventListener('submit', function() {
        var btn = document.getElementById('tombolUjiCnn');
        btn.disabled = true;
        btn.textContent = 'Memproses... Mohon tunggu.';
    });
    </script>

    <p style="color:var(--warna-teks-sekunder);font-size:13px;margin-top:16px;margin-bottom:0;">
      Ulangi form ini untuk tiap skenario tambahan (nomor skenario bertambah otomatis).
    </p>
  </div>

  <section>
    <h2>Tabel Hasil Pengujian CNN MobileNetV2</h2>
    <p style="color:var(--warna-teks-sekunder);">Pengujian dilakukan dengan target 30 skenario gambar uji.</p>
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
      <form method="POST" onsubmit="return confirm('Hapus semua skenario CNN?');">
        <input type="hidden" name="hapus_semua_skenario_cnn" value="1">
        <button type="submit" class="tombol-hapus">🗑 Hapus Semua CNN</button>
      </form>
    </div>
    <div class="tabel-bungkus">
    <table>
      <thead>
      <tr><th>Skenario</th><th>Gambar Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th></tr>
      </thead>
      <tbody>
      <?php foreach ($data_cnn as $b): ?>
      <tr>
        <td><?= $b['nomor_skenario'] ?></td><td style="text-align:left;white-space:normal;"><?= htmlspecialchars($b['nama_gambar_uji']) ?></td>
        <td><?= number_format($b['presisi_5'], 3) ?></td><td><?= number_format($b['presisi_10'], 3) ?></td>
        <td><?= number_format($b['ingatan_5'], 3) ?></td><td><?= number_format($b['ingatan_10'], 3) ?></td>
        <td><?= number_format($b['skor_f1_5'], 3) ?></td><td><?= number_format($b['skor_f1_10'], 3) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data_cnn): ?><tr><td colspan="8">Belum ada data pengujian CNN.</td></tr><?php endif; ?>
      </tbody>
      <?php if ($data_cnn): ?>
      <tfoot>
      <tr class="baris-rerata">
        <td colspan="2">Rata-rata</td>
        <td><?= number_format($rerata_cnn['presisi_5'], 3) ?></td><td><?= number_format($rerata_cnn['presisi_10'], 3) ?></td>
        <td><?= number_format($rerata_cnn['ingatan_5'], 3) ?></td><td><?= number_format($rerata_cnn['ingatan_10'], 3) ?></td>
        <td><?= number_format($rerata_cnn['skor_f1_5'], 3) ?></td><td><?= number_format($rerata_cnn['skor_f1_10'], 3) ?></td>
      </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
  </section>

  <section>
    <h2>Grafik Rata-rata Metrik CNN</h2>
    <div class="kartu-melayang" style="padding:24px;">
      <canvas id="grafikCnn" height="90"></canvas>
    </div>
  </section>

    </div>
  </div>
</div>

<script>
const rataCnn = <?= json_encode($rerata_cnn) ?>;
const label = ['Presisi@5','Presisi@10','Ingatan@5','Ingatan@10','Skor-F1@5','Skor-F1@10'];

new Chart(document.getElementById('grafikCnn'), {
  type: 'bar',
  data: {
    labels: label,
    datasets: [{ label: 'Nilai rata-rata CNN', data: Object.values(rataCnn), backgroundColor: '#4F46E5', borderRadius: 8 }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'top' } },
    scales: { y: { beginAtZero: true, max: 1 } }
  }
});

</script>
</body>
</html>

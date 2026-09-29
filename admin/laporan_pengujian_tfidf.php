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
$desain_dengan_tfidf = $koneksi->query("SELECT COUNT(*) FROM desain_undangan WHERE vektor_fitur_tfidf IS NOT NULL AND vektor_fitur_tfidf <> ''")->fetchColumn();
$desain_dengan_kategori_tema = $koneksi->query("SELECT COUNT(*) FROM desain_undangan WHERE id_kategori IS NOT NULL AND tema IS NOT NULL")->fetchColumn();
$daftar_desain_uji = $koneksi->query(
    "SELECT d.id_desain, d.judul_desain, d.kata_kunci, d.id_kategori, d.tema, k.nama_kategori
     FROM desain_undangan d
     LEFT JOIN kategori_desain k ON k.id_kategori = d.id_kategori
     WHERE d.id_kategori IS NOT NULL AND d.tema IS NOT NULL AND d.kata_kunci IS NOT NULL AND TRIM(d.kata_kunci) <> ''
     ORDER BY d.judul_desain, d.id_desain"
)->fetchAll();

    // ---- Menjalankan pengujian TF-IDF otomatis memakai desain dari katalog ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_uji_tfidf'])) {
        $id_desain_uji = (int) ($_POST['id_desain_uji_tfidf'] ?? 0);
        $stmt = $koneksi->prepare(
            "SELECT id_desain, judul_desain, kata_kunci, id_kategori, tema
             FROM desain_undangan
             WHERE id_desain = ? AND id_kategori IS NOT NULL AND tema IS NOT NULL
               AND kata_kunci IS NOT NULL AND TRIM(kata_kunci) <> ''"
        );
        $stmt->execute([$id_desain_uji]);
        $desain_uji = $stmt->fetch();

        if (!$desain_uji) {
            $pesan = 'Pilih desain katalog yang valid untuk dijadikan skenario uji.';
            $jenis_pesan = 'gagal';
        } else {
            $kata_kunci_uji = trim($desain_uji['kata_kunci']);
            $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/cari_berdasarkan_teks');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['kata_kunci' => $kata_kunci_uji]));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            $tanggapan = curl_exec($curl);
            $kode_http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

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
                        [$p5, $p10, $i5, $i10, $f5, $f10] = hitung_metrik_evaluasi(
                          $hasil,
                          (int) $desain_uji['id_kategori'],
                          (string) $desain_uji['tema'],
                          $koneksi
                        );

                        $no_skenario = nomor_skenario_berikutnya($koneksi, 'pengujian_tfidf');
                        $stmt = $koneksi->prepare(
                            "INSERT INTO pengujian_tfidf
                            (nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([$no_skenario, $kata_kunci_uji, $p5, $p10, $i5, $i10, $f5, $f10]);

                        $pesan = "Skenario TF-IDF #$no_skenario tersimpan otomatis — Presisi@5: " . number_format($p5, 2)
                            . ", Presisi@10: " . number_format($p10, 2) . ", Ingatan@10: " . number_format($i10, 2)
                            . ", Skor-F1@10: " . number_format($f10, 2) . ".";
                        $jenis_pesan = 'sukses';
                    }
                }
            }
        }
    }

// ---- Menjalankan SEMUA skenario TF-IDF otomatis sekaligus, memakai desain yang sudah ada di katalog ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jalankan_uji_massal_tfidf'])) {
    $hasil_tfidf = jalankan_evaluasi_massal($koneksi, 'tfidf');

    if (!$hasil_tfidf) {
        $pesan = 'Belum ada desain dengan kategori & tema terisi untuk dijadikan bahan uji otomatis. Lengkapi data desain (kategori & tema) di menu Kelola Desain terlebih dahulu.';
        $jenis_pesan = 'gagal';
    } else {
        $jumlah = 0;
        foreach ($hasil_tfidf as $baris) {
            $no = nomor_skenario_berikutnya($koneksi, 'pengujian_tfidf');
            $koneksi->prepare(
                "INSERT INTO pengujian_tfidf
                (nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$no, $baris['kata_kunci_uji'], $baris['presisi_5'], $baris['presisi_10'], $baris['ingatan_5'], $baris['ingatan_10'], $baris['skor_f1_5'], $baris['skor_f1_10']]);
            $jumlah++;
        }
        $pesan = "Pengujian massal TF-IDF selesai — $jumlah skenario otomatis dijalankan & disimpan.";
        $jenis_pesan = 'sukses';
    }
}

if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['impor_pengujian_tfidf']) && !empty($_FILES['berkas_tfidf']['name'])) {
    [$diimpor, $dilewati] = impor_csv_pengujian($koneksi, 'tfidf', $_FILES['berkas_tfidf']);
    $pesan = "Impor CSV selesai — $diimpor baris diimpor" . ($dilewati ? ", $dilewati dilewati (kolom kosong)" : '') . ".";
    $jenis_pesan = 'sukses';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_semua_skenario_tfidf'])) {
    $koneksi->prepare("DELETE FROM pengujian_tfidf")->execute();
    $pesan = 'Semua skenario TF-IDF berhasil dihapus.';
    $jenis_pesan = 'sukses';
}

$data_tfidf = $koneksi->query(
    "SELECT nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10
     FROM pengujian_tfidf ORDER BY nomor_skenario"
)->fetchAll();

$rerata_tfidf = [
    'presisi_5' => rerata_metrik($data_tfidf, 'presisi_5'), 'presisi_10' => rerata_metrik($data_tfidf, 'presisi_10'),
    'ingatan_5' => rerata_metrik($data_tfidf, 'ingatan_5'), 'ingatan_10' => rerata_metrik($data_tfidf, 'ingatan_10'),
    'skor_f1_5' => rerata_metrik($data_tfidf, 'skor_f1_5'), 'skor_f1_10' => rerata_metrik($data_tfidf, 'skor_f1_10'),
];

$jumlah_skenario_tfidf = count($data_tfidf);
$target_skenario = 30;
$progres_persen = min(100, round(($jumlah_skenario_tfidf / $target_skenario) * 100));

$halaman_admin_aktif = 'laporan_tfidf';
$judul_halaman_admin = 'Laporan Pengujian TF-IDF';
$subjudul_halaman_admin = 'Hasil pengujian metode TF-IDF + Kesamaan Kosinus (Precision / Recall / F1-Score)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pengujian TF-IDF - Admin Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
  .tabel-bungkus { overflow-x:auto; border-radius:12px; border:1px solid var(--warna-batas); margin:16px 0 32px; }
  table { width:100%; border-collapse:collapse; background:#fff; min-width:720px; }
  th, td { padding:10px 12px; text-align:center; border-bottom:1px solid var(--warna-batas); font-size:14px; white-space:nowrap; }
  th { background:var(--warna-latar-alt); color:var(--warna-teks-utama); position:sticky; top:0; font-size:13px; }
  tbody tr:nth-child(even) { background:#fafafa; }
  tbody tr:hover { background:#fff7ed; }
  tr.baris-rerata td { font-weight:700; background:#fff7ed; border-top:2px solid #F59E0B; }
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
  .bar-progres .isi { height:100%; background:#F59E0B; }
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
    Dengan vektor TF-IDF: <?= (int) $desain_dengan_tfidf ?> |
    Dengan kategori & tema: <?= (int) $desain_dengan_kategori_tema ?>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:18px;">
    <a href="<?= BASE_URL ?>/pengujian/uji_cnn.php" class="tombol-garis">↔ Lihat Laporan CNN</a>
    <a href="<?= BASE_URL ?>/admin/ekspor_laporan.php?format=pdf&internal=1" target="_blank" rel="noopener" class="tombol-utama" style="text-decoration:none;">📑 Preview Hasil CNN/TF-IDF</a>
  </div>

  <div class="kartu-ringkasan">
    <div class="kartu-mini">
      <div class="label">Skenario Tercatat</div>
      <div class="nilai"><?= $jumlah_skenario_tfidf ?> / <?= $target_skenario ?></div>
      <div class="bar-progres"><div class="isi" style="width:<?= $progres_persen ?>%;"></div></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Presisi@10</div>
      <div class="nilai"><?= number_format($rerata_tfidf['presisi_10'], 3) ?></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Ingatan@10</div>
      <div class="nilai"><?= number_format($rerata_tfidf['ingatan_10'], 3) ?></div>
    </div>
    <div class="kartu-mini">
      <div class="label">Rata-rata Skor-F1@10</div>
      <div class="nilai"><?= number_format($rerata_tfidf['skor_f1_10'], 3) ?></div>
    </div>
  </div>

  <div class="kartu-melayang" style="padding:24px;margin-bottom:24px;border:2px solid #F59E0B;">
    <h2 style="margin-top:0;color:var(--warna-teks-utama);">🚀 Jalankan Semua Skenario TF-IDF Otomatis</h2>
    <p style="color:var(--warna-teks-sekunder);font-size:13px;margin-top:-6px;">
      Memakai kata kunci desain yang SUDAH ADA di katalog (dengan kategori & tema terisi) sebagai
      bahan uji — kategori/tema desain itu sendiri jadi target yang diharapkan. Boleh diklik
      berkali-kali kapan saja; skenario baru ditambahkan, bukan menimpa yang lama.
    </p>
    <form method="POST" id="formMassalTfidf">
      <input type="hidden" name="jalankan_uji_massal_tfidf" value="1">
      <button type="submit" class="tombol-utama" id="tombolMassalTfidf">🚀 Jalankan Semua Skenario TF-IDF Sekarang</button>
    </form>

    <script>
    document.getElementById('formMassalTfidf').addEventListener('submit', function() {
        var btn = document.getElementById('tombolMassalTfidf');
        btn.disabled = true;
        btn.textContent = 'Memproses... Mohon tunggu.';
    });
    </script>
  </div>

  <div class="kartu-melayang" style="padding:24px;margin-bottom:32px;">
    <h2 style="margin-top:0;color:var(--warna-teks-utama);">Uji Skenario Tunggal (Opsional)</h2>
    <p style="color:var(--warna-teks-sekunder);margin-top:-8px;">
      Pilih desain yang sudah ada di katalog agar kata kunci uji, kategori, dan tema ground-truth
      konsisten. Kalau cuma ingin memenuhi 30 skenario untuk skripsi, tombol di atas biasanya sudah cukup.
    </p>

    <form method="POST" action="<?= BASE_URL ?>/admin/laporan_pengujian_tfidf.php" style="max-width:520px;border:1px solid var(--warna-batas);border-radius:12px;padding:18px;margin-top:16px;" id="formUjiTfidf">
      <input type="hidden" name="jalankan_uji_tfidf" value="1">
      <label>Desain Katalog sebagai Skenario Uji</label>
      <select name="id_desain_uji_tfidf" required style="width:100%;margin-bottom:14px;padding:8px;">
        <option value="">-- Pilih desain --</option>
        <?php foreach ($daftar_desain_uji as $d): ?>
          <option value="<?= $d['id_desain'] ?>">
            <?= htmlspecialchars($d['judul_desain']) ?> — <?= htmlspecialchars($d['nama_kategori'] ?? 'Tanpa kategori') ?> / <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $d['tema']))) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="tombol-utama" style="width:100%;" id="tombolUjiTfidf">Jalankan Uji TF-IDF</button>
    </form>

    <script>
    document.getElementById('formUjiTfidf').addEventListener('submit', function() {
        var btn = document.getElementById('tombolUjiTfidf');
        btn.disabled = true;
        btn.textContent = 'Memproses... Mohon tunggu.';
    });
    </script>

    <p style="color:var(--warna-teks-sekunder);font-size:13px;margin-top:16px;margin-bottom:0;">
      Ulangi form ini untuk tiap skenario tambahan (nomor skenario bertambah otomatis).
    </p>
  </div>

  <!-- Fitur impor CSV dinonaktifkan dari panel admin.
  <div class="kartu-melayang" style="padding:24px;margin-bottom:32px;">
    <h2 style="margin-top:0;color:var(--warna-teks-utama);">Impor dari CSV (Legacy/Opsional)</h2>
    <p style="color:var(--warna-teks-sekunder);margin-top:-8px;">
      Kalau sudah punya angka pengujian TF-IDF nyata dari sumber lain (mis. lampiran skripsi terpisah),
      unggah di sini. Kolom CSV: <code>nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10</code>.
    </p>
    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px;">
      <input type="hidden" name="impor_pengujian_tfidf" value="1">
      <input type="file" name="berkas_tfidf" accept=".csv" required>
      <button type="submit" class="tombol-garis">⬆ Impor CSV TF-IDF</button>
    </form>
  </div>
  -->

  <section>
    <h2>Tabel Hasil Pengujian TF-IDF + Kesamaan Kosinus</h2>
    <p style="color:var(--warna-teks-sekunder);">Pengujian dilakukan dengan target 30 skenario kata kunci.</p>
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
      <form method="POST" onsubmit="return confirm('Hapus semua skenario TF-IDF?');">
        <input type="hidden" name="hapus_semua_skenario_tfidf" value="1">
        <button type="submit" class="tombol-hapus">🗑 Hapus Semua TF-IDF</button>
      </form>
    </div>
    <div class="tabel-bungkus">
    <table>
      <thead>
      <tr><th>Skenario</th><th>Kata Kunci Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th></tr>
      </thead>
      <tbody>
      <?php foreach ($data_tfidf as $b): ?>
      <tr>
        <td><?= $b['nomor_skenario'] ?></td><td style="text-align:left;white-space:normal;"><?= htmlspecialchars($b['kata_kunci_uji']) ?></td>
        <td><?= number_format($b['presisi_5'], 3) ?></td><td><?= number_format($b['presisi_10'], 3) ?></td>
        <td><?= number_format($b['ingatan_5'], 3) ?></td><td><?= number_format($b['ingatan_10'], 3) ?></td>
        <td><?= number_format($b['skor_f1_5'], 3) ?></td><td><?= number_format($b['skor_f1_10'], 3) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$data_tfidf): ?><tr><td colspan="8">Belum ada data pengujian TF-IDF.</td></tr><?php endif; ?>
      </tbody>
      <?php if ($data_tfidf): ?>
      <tfoot>
      <tr class="baris-rerata">
        <td colspan="2">Rata-rata</td>
        <td><?= number_format($rerata_tfidf['presisi_5'], 3) ?></td><td><?= number_format($rerata_tfidf['presisi_10'], 3) ?></td>
        <td><?= number_format($rerata_tfidf['ingatan_5'], 3) ?></td><td><?= number_format($rerata_tfidf['ingatan_10'], 3) ?></td>
        <td><?= number_format($rerata_tfidf['skor_f1_5'], 3) ?></td><td><?= number_format($rerata_tfidf['skor_f1_10'], 3) ?></td>
      </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
  </section>

  <section>
    <h2>Grafik Rata-rata Metrik TF-IDF</h2>
    <div class="kartu-melayang" style="padding:24px;">
      <canvas id="grafikTfidf" height="90"></canvas>
    </div>
  </section>

    </div>
  </div>
</div>

<script>
const rataTfidf = <?= json_encode($rerata_tfidf) ?>;
const label = ['Presisi@5','Presisi@10','Ingatan@5','Ingatan@10','Skor-F1@5','Skor-F1@10'];

new Chart(document.getElementById('grafikTfidf'), {
  type: 'bar',
  data: {
    labels: label,
    datasets: [{ label: 'Nilai rata-rata TF-IDF', data: Object.values(rataTfidf), backgroundColor: '#F59E0B', borderRadius: 8 }]
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

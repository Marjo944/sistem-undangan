<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$halaman_admin_aktif = 'dashboard';
$judul_halaman_admin = 'Kelola Data Pesanan';

// Tanggal Indonesia untuk subjudul
$nama_hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$sekarang = new DateTime();
$subjudul_halaman_admin = $nama_hari[(int)$sekarang->format('w')] . ', ' . (int)$sekarang->format('j') . ' ' .
    $nama_bulan[(int)$sekarang->format('n')] . ' ' . $sekarang->format('Y') . ' · Ringkasan aktivitas hari ini';

/* ================= STATISTIK KARTU ================= */
try {
    $total_desain = (int) $koneksi->query("SELECT COUNT(*) c FROM desain_undangan")->fetch()['c'];
    $desain_baru_bulan_ini = (int) $koneksi->query(
        "SELECT COUNT(*) c FROM desain_undangan WHERE MONTH(dibuat_pada)=MONTH(CURDATE()) AND YEAR(dibuat_pada)=YEAR(CURDATE())"
    )->fetch()['c'];

    $pesanan_diproses = (int) $koneksi->query(
      "SELECT COUNT(*) c FROM pesanan WHERE status='diproses'"
    )->fetch()['c'];

    $pencarian_hari_ini = (int) $koneksi->query(
      "SELECT COUNT(*) c FROM riwayat_pencarian WHERE DATE(dicari_pada)=CURDATE()"
    )->fetch()['c'];
    $pencarian_teks_hari_ini = (int) $koneksi->query(
      "SELECT COUNT(*) c FROM riwayat_pencarian WHERE jenis_pencarian='teks' AND DATE(dicari_pada)=CURDATE()"
    )->fetch()['c'];
    $pencarian_gambar_hari_ini = (int) $koneksi->query(
      "SELECT COUNT(*) c FROM riwayat_pencarian WHERE jenis_pencarian='gambar' AND DATE(dicari_pada)=CURDATE()"
    )->fetch()['c'];

    $menunggu_dp = (int) $koneksi->query(
        "SELECT COUNT(*) c FROM pesanan WHERE status='menunggu_dp'"
    )->fetch()['c'];

    $selesai_bulan_ini = (int) $koneksi->query(
        "SELECT COUNT(*) c FROM pesanan WHERE status='selesai' AND MONTH(diperbarui_pada)=MONTH(CURDATE()) AND YEAR(diperbarui_pada)=YEAR(CURDATE())"
    )->fetch()['c'];
    $selesai_bulan_lalu = (int) $koneksi->query(
        "SELECT COUNT(*) c FROM pesanan WHERE status='selesai' AND MONTH(diperbarui_pada)=MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(diperbarui_pada)=YEAR(CURDATE() - INTERVAL 1 MONTH)"
    )->fetch()['c'];
    $persen_selesai = $selesai_bulan_lalu > 0
        ? round((($selesai_bulan_ini - $selesai_bulan_lalu) / $selesai_bulan_lalu) * 100)
        : null;

    /* ================= PESANAN TERBARU ================= */
    $pesanan_terbaru = $koneksi->query(
        "SELECT p.id_pesanan, p.status, p.nama_pelanggan, d.judul_desain
         FROM pesanan p
         LEFT JOIN desain_undangan d ON d.id_desain = p.id_desain
         ORDER BY p.dibuat_pada DESC LIMIT 5"
    )->fetchAll();
} catch (Exception $e) {
    // Tabel pesanan belum tersedia — impor ulang database/rotama_jaya_online.sql (sudah lengkap)
    $total_desain = $total_desain ?? 0;
    $desain_baru_bulan_ini = $desain_baru_bulan_ini ?? 0;
    $pesanan_diproses = 0; $menunggu_dp = 0; $selesai_bulan_ini = 0; $persen_selesai = null;
    $pesanan_terbaru = [];
    $galat_tabel_pesanan = true;
    $pencarian_hari_ini = $pencarian_hari_ini ?? 0;
    $pencarian_teks_hari_ini = $pencarian_teks_hari_ini ?? 0;
    $pencarian_gambar_hari_ini = $pencarian_gambar_hari_ini ?? 0;
}

/* ================= AKTIVITAS PENCARIAN (grafik kanan) ================= */
$rentang_hari = isset($_GET['rentang']) && (int)$_GET['rentang'] === 30 ? 30 : 7;
$data_grafik_pencarian = [];
$total_pencarian_periode = 0;
try {
  $stmt = $koneksi->prepare("SELECT jenis_pencarian, COUNT(*) AS jumlah
    FROM riwayat_pencarian
    WHERE dicari_pada >= (NOW() - INTERVAL ? DAY)
    GROUP BY jenis_pencarian");
  $stmt->execute([$rentang_hari]);
  foreach ($stmt->fetchAll() as $baris) {
    $nama = $baris['jenis_pencarian'] === 'gambar' ? 'CNN / Gambar' : 'TF-IDF / Teks';
    $data_grafik_pencarian[] = ['nama' => $nama, 'jumlah' => (int) $baris['jumlah']];
    $total_pencarian_periode += (int) $baris['jumlah'];
  }
} catch (Throwable $e) {
  $data_grafik_pencarian = [];
}
$maks_pencarian_periode = $data_grafik_pencarian ? max(array_column($data_grafik_pencarian, 'jumlah')) : 0;

$peta_status = [
    'diproses'    => ['label' => 'Diproses',     'kelas' => 'diproses'],
    'selesai'     => ['label' => 'Selesai',      'kelas' => 'selesai'],
    'menunggu_dp' => ['label' => 'Menunggu DP',  'kelas' => 'menunggu_dp'],
];

/* ================= GRAFIK: PESANAN PER STATUS ================= */
$data_grafik_status = [];
$total_semua_pesanan = 0;
try {
    $baris_status = $koneksi->query(
        "SELECT status, COUNT(*) c FROM pesanan GROUP BY status"
    )->fetchAll();
    $peta_hasil = array_column($baris_status, 'c', 'status');
    foreach ($peta_status as $kunci => $info) {
        $jumlah = (int) ($peta_hasil[$kunci] ?? 0);
        $data_grafik_status[] = ['nama' => $info['label'], 'kelas' => $info['kelas'], 'jumlah' => $jumlah];
        $total_semua_pesanan += $jumlah;
    }
} catch (Exception $e) {
    $data_grafik_status = [];
}

/* ================= GRAFIK: DESAIN TERPOPULER =================
 * "Terpopuler" diukur dari jumlah pesanan yang memakai desain tsb
 * (riwayat_pencarian tidak menyimpan id_desain per klik, jadi pesanan
 * adalah sinyal minat pelanggan yang paling dapat diandalkan). */
try {
    $desain_terpopuler = $koneksi->query(
        "SELECT d.judul_desain, COUNT(p.id_pesanan) AS jumlah_dipesan
         FROM pesanan p
         JOIN desain_undangan d ON d.id_desain = p.id_desain
         GROUP BY d.id_desain, d.judul_desain
         ORDER BY jumlah_dipesan DESC
         LIMIT 5"
    )->fetchAll();
} catch (Exception $e) {
    $desain_terpopuler = [];
}
$maks_dipesan = $desain_terpopuler ? max(array_column($desain_terpopuler, 'jumlah_dipesan')) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="tata-letak-admin">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

  <div class="konten-admin">
    <?php include __DIR__ . '/../includes/topbar_admin.php'; ?>

    <div class="isi-admin">

      <?php if (!empty($galat_tabel_pesanan)): ?>
        <div class="pesan-admin gagal">
          Tabel <code>pesanan</code> belum ada di database. Jalankan
          <code>database/rotama_jaya_online.sql</code> lalu muat ulang halaman ini.
        </div>
      <?php endif; ?>

      <div class="grid-statistik">
        <div class="kartu-statistik">
          <div class="label-statistik">Total Desain</div>
          <div class="angka-statistik"><?= number_format($total_desain, 0, ',', '.') ?></div>
          <div class="ket-statistik naik">▲ <?= $desain_baru_bulan_ini ?> baru bulan ini</div>
        </div>
        <div class="kartu-statistik warna-oranye">
          <div class="label-statistik">Pesanan Diproses</div>
          <div class="angka-statistik"><?= number_format($pesanan_diproses, 0, ',', '.') ?></div>
          <div class="ket-statistik oranye">Status pesanan: diproses</div>
        </div>
        <div class="kartu-statistik warna-merah">
          <div class="label-statistik">Menunggu DP</div>
          <div class="angka-statistik"><?= number_format($menunggu_dp, 0, ',', '.') ?></div>
          <div class="ket-statistik merah">Perlu konfirmasi</div>
        </div>
        <div class="kartu-statistik warna-hijau">
          <div class="label-statistik">Selesai Bulan Ini</div>
          <div class="angka-statistik"><?= number_format($selesai_bulan_ini, 0, ',', '.') ?></div>
          <div class="ket-statistik <?= $persen_selesai === null ? 'netral' : ($persen_selesai >= 0 ? 'naik' : 'merah') ?>">
            <?php if ($persen_selesai === null): ?>
              Belum ada data bulan lalu
            <?php else: ?>
              <?= $persen_selesai >= 0 ? '▲' : '▼' ?> <?= abs($persen_selesai) ?>% dari bulan lalu
            <?php endif; ?>
          </div>
         </div>
        <div class="kartu-statistik warna-biru">
          <div class="label-statistik">Pencarian Hari Ini</div>
          <div class="angka-statistik"><?= number_format($pencarian_hari_ini, 0, ',', '.') ?></div>
          <div class="ket-statistik netral">
            <?= $pencarian_gambar_hari_ini ?> CNN · <?= $pencarian_teks_hari_ini ?> TF-IDF
          </div>
        </div>
       </div>

       <div class="grid-dasbor">
        <div class="panel-admin">
          <div class="judul-panel">
            <h3>Pesanan Terbaru</h3>
            <a href="<?= BASE_URL ?>/admin/kelola_pesanan.php">Lihat Semua &rsaquo;</a>
          </div>

          <?php if (empty($pesanan_terbaru)): ?>
            <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum ada pesanan yang masuk.</p>
          <?php else: ?>
          <table class="tabel-admin">
            <thead>
              <tr><th>ID</th><th>Pelanggan</th><th>Desain</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              <?php foreach ($pesanan_terbaru as $p): $st = $peta_status[$p['status']]; ?>
              <tr>
                <td>#<?= str_pad($p['id_pesanan'], 4, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($p['nama_pelanggan']) ?></td>
                <td><?= htmlspecialchars($p['judul_desain'] ?? '—') ?></td>
                <td><span class="lencana-status <?= $st['kelas'] ?>"><?= $st['label'] ?></span></td>
                <td><a class="aksi-admin" href="<?= BASE_URL ?>/admin/kelola_pesanan.php?edit=<?= $p['id_pesanan'] ?>">Update &rsaquo;</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <div class="panel-admin">
          <div class="judul-panel">
            <h3>Aktivitas Pencarian</h3>
            <form method="GET">
              <select name="rentang" onchange="this.form.submit()">
                <option value="7" <?= $rentang_hari === 7 ? 'selected' : '' ?>>7 hari</option>
                <option value="30" <?= $rentang_hari === 30 ? 'selected' : '' ?>>30 hari</option>
              </select>
            </form>
          </div>

          <?php if (empty($data_grafik_pencarian)): ?>
            <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum cukup data pencarian pada periode ini.</p>
          <?php else: ?>
          <div class="grafik-batang-kategori">
            <?php foreach ($data_grafik_pencarian as $item):
              $tinggi = $maks_pencarian_periode > 0 ? round(($item['jumlah'] / $maks_pencarian_periode) * 100) : 0;
              $tinggi = max(18, $tinggi); // tinggi minimum agar tetap terlihat
            ?>
              <div class="batang-kategori">
                <div class="isi-batang" style="height:<?= $tinggi ?>%;"><?= $item['jumlah'] ?>x</div>
                <div class="label-batang"><?= htmlspecialchars($item['nama']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="grid-dasbor" style="margin-top:20px;">
        <div class="panel-admin">
          <div class="judul-panel">
            <h3>Pesanan per Status</h3>
          </div>
          <?php if ($total_semua_pesanan === 0): ?>
            <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum ada pesanan yang tercatat.</p>
          <?php else: ?>
          <div class="grafik-batang-kategori">
            <?php foreach ($data_grafik_status as $item):
              $persen = $total_semua_pesanan > 0 ? round(($item['jumlah'] / $total_semua_pesanan) * 100) : 0;
              $tinggi = max(10, $persen);
              $warna_batang = [
                'diproses' => 'var(--admin-biru, #2563eb)',
                'selesai' => 'var(--admin-hijau)',
                'menunggu_dp' => 'var(--admin-oranye)',
              ][$item['kelas']] ?? 'var(--warna-utama)';
            ?>
              <div class="batang-kategori">
                <div class="isi-batang" style="height:<?= $tinggi ?>%;background:<?= $warna_batang ?>;">
                  <?= $item['jumlah'] ?>
                </div>
                <div class="label-batang"><?= htmlspecialchars($item['nama']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="margin-top:10px;font-size:12px;color:var(--warna-teks-sekunder);">
            Total <?= number_format($total_semua_pesanan, 0, ',', '.') ?> pesanan sepanjang waktu.
          </p>
          <?php endif; ?>
        </div>

        <div class="panel-admin">
          <div class="judul-panel">
            <h3>Desain Terpopuler</h3>
          </div>
          <?php if (empty($desain_terpopuler)): ?>
            <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum ada desain yang terpakai di pesanan manapun.</p>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($desain_terpopuler as $d):
              $persen_lebar = $maks_dipesan > 0 ? round(($d['jumlah_dipesan'] / $maks_dipesan) * 100) : 0;
            ?>
              <div>
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                  <span><?= htmlspecialchars($d['judul_desain']) ?></span>
                  <span style="color:var(--warna-teks-sekunder);font-weight:700;"><?= $d['jumlah_dipesan'] ?>x</span>
                </div>
                <div style="background:var(--warna-latar-alt, #f1f5f9);border-radius:6px;height:8px;overflow:hidden;">
                  <div style="width:<?= max(6, $persen_lebar) ?>%;height:100%;background:var(--warna-utama);"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="margin-top:14px;font-size:12px;color:var(--warna-teks-sekunder);">
            Dihitung dari jumlah desain dipakai di pesanan pelanggan.
          </p>
          <?php endif; ?>
        </div>
      </div>

     </div>
   </div>
 </div>

 </body>
</html>

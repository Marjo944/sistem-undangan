<?php
/**
 * LOG_AKTIVITAS.PHP
 * Audit trail sederhana: menampilkan riwayat aksi admin (tambah/ubah/hapus)
 * yang tercatat di tabel log_aktivitas. Bisa difilter per modul dan dicari
 * berdasarkan teks keterangan/nama admin.
 * Hanya bisa diakses oleh peran 'admin'.
 */
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin_hanya_admin();

$halaman_admin_aktif    = 'log_aktivitas';
$judul_halaman_admin    = 'Log Aktivitas';
$subjudul_halaman_admin = 'Riwayat aksi admin di sistem (audit trail)';

if (!tabel_ada($koneksi, 'log_aktivitas')) { ?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<title>Log Aktivitas — Rotama Jaya</title></head>
<body><div class="tata-letak-admin">
<?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>
<div class="konten-admin"><?php include __DIR__ . '/../includes/topbar_admin.php'; ?>
<div class="isi-admin">
<div class="pesan-admin gagal">
  <b>Tabel <code>log_aktivitas</code> belum ada.</b><br>
  Impor ulang <code>database/rotama_jaya_online.sql</code> lewat phpMyAdmin
  agar tabel log tersedia.
</div></div></div></div></body></html>
<?php exit; }

/* ===== FILTER ===== */
$modul_dipilih = $_GET['modul'] ?? '';
$kata_cari     = trim($_GET['cari'] ?? '');
$modul_valid   = ['pesanan', 'desain', 'kategori', 'admin', 'pengaturan', 'auth'];

/* ===== HAPUS SEMUA LOG ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_semua_log'])) {
  $koneksi->exec('DELETE FROM log_aktivitas');
  header('Location: ' . BASE_URL . '/admin/log_aktivitas.php');
  exit;
}

$kondisi = [];
$parameter = [];

if ($modul_dipilih !== '' && in_array($modul_dipilih, $modul_valid, true)) {
    $kondisi[] = 'modul = ?';
    $parameter[] = $modul_dipilih;
}
if ($kata_cari !== '') {
    $kondisi[] = '(keterangan LIKE ? OR nama_admin_saat_itu LIKE ?)';
    $parameter[] = '%' . $kata_cari . '%';
    $parameter[] = '%' . $kata_cari . '%';
}
$klausa_where = $kondisi ? ('WHERE ' . implode(' AND ', $kondisi)) : '';

/* ===== PAGINASI SEDERHANA ===== */
$per_halaman = 30;
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$offset = ($halaman - 1) * $per_halaman;

$stmt_total = $koneksi->prepare("SELECT COUNT(*) c FROM log_aktivitas $klausa_where");
$stmt_total->execute($parameter);
$total_baris = (int) $stmt_total->fetch()['c'];
$total_halaman = max(1, (int) ceil($total_baris / $per_halaman));

$stmt = $koneksi->prepare(
  "SELECT id_log, dicatat_pada, nama_admin_saat_itu, modul, keterangan
     FROM log_aktivitas $klausa_where
     ORDER BY dicatat_pada DESC
     LIMIT $per_halaman OFFSET $offset"
);
$stmt->execute($parameter);
$daftar_log = $stmt->fetchAll();

$label_modul = [
    'pesanan'    => ['teks' => 'Pesanan',    'warna' => '#DBEAFE', 'teks_warna' => '#1D4ED8'],
    'desain'     => ['teks' => 'Desain',     'warna' => '#F3E8FF', 'teks_warna' => '#7E22CE'],
    'kategori'   => ['teks' => 'Kategori',   'warna' => '#FEF3C7', 'teks_warna' => '#B45309'],
    'admin'      => ['teks' => 'Akun Admin', 'warna' => '#FCE7F3', 'teks_warna' => '#BE185D'],
    'pengaturan' => ['teks' => 'Pengaturan', 'warna' => '#E0E7FF', 'teks_warna' => '#4338CA'],
    'auth'       => ['teks' => 'Login',      'warna' => '#DCFCE7', 'teks_warna' => '#15803D'],
];

function pertahankan_query_string(array $ganti = []): string {
    $gabung = array_merge($_GET, $ganti);
    return htmlspecialchars('?' . http_build_query($gabung));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log Aktivitas - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
.lencana-modul { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; white-space:nowrap; }
.filter-log { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center; }
.filter-log select, .filter-log input[type=text] {
  padding:9px 12px; border-radius:10px; border:1px solid var(--warna-batas); font-size:13px;
}
.filter-log input[type=text] { min-width:220px; }
.paginasi-log { display:flex; gap:8px; align-items:center; justify-content:center; margin-top:18px; font-size:13px; }
.paginasi-log a, .paginasi-log span {
  padding:6px 12px; border-radius:8px; border:1px solid var(--warna-batas); text-decoration:none; color:var(--warna-teks-utama);
}
.paginasi-log .aktif { background: var(--warna-utama); color:#fff; border-color: var(--warna-utama); }
.waktu-log { color: var(--warna-teks-sekunder); font-size:12px; white-space:nowrap; }
.aksi-hapus-semua-log { border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; padding:9px 14px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
.aksi-hapus-semua-log:hover { background:#fee2e2; }
</style>
</head>
<body>
<div class="tata-letak-admin">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

  <div class="konten-admin">
    <?php include __DIR__ . '/../includes/topbar_admin.php'; ?>

    <div class="isi-admin">

      <div class="panel-admin">
        <div class="judul-panel">
          <h3>Riwayat Aktivitas (<?= number_format($total_baris, 0, ',', '.') ?>)</h3>
        </div>

        <form method="GET" class="filter-log">
          <select name="modul" onchange="this.form.submit()">
            <option value="">Semua Modul</option>
            <?php foreach ($modul_valid as $m): ?>
              <option value="<?= $m ?>" <?= $modul_dipilih === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($label_modul[$m]['teks']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="cari" placeholder="Cari keterangan atau nama admin..." value="<?= htmlspecialchars($kata_cari) ?>">
          <button type="submit" class="tombol-utama" style="padding:9px 16px;">Cari</button>
          <button type="submit" name="hapus_semua_log" value="1" formmethod="POST" class="aksi-hapus-semua-log"
            onclick="return confirm('Hapus semua log aktivitas? Tindakan ini tidak dapat dibatalkan.');">
            Hapus Semua Log
          </button>
          <?php if ($modul_dipilih !== '' || $kata_cari !== ''): ?>
            <a href="<?= BASE_URL ?>/admin/log_aktivitas.php" class="tombol-garis" style="padding:9px 16px;">Reset</a>
          <?php endif; ?>
        </form>

        <?php if (empty($daftar_log)): ?>
          <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum ada aktivitas yang cocok dengan filter ini.</p>
        <?php else: ?>
        <table class="tabel-admin">
          <thead>
            <tr><th>Waktu</th><th>Admin</th><th>Modul</th><th>Keterangan</th></tr>
          </thead>
          <tbody>
            <?php foreach ($daftar_log as $log): $m = $label_modul[$log['modul']] ?? ['teks' => $log['modul'], 'warna' => '#F3F4F6', 'teks_warna' => '#374151']; ?>
            <tr>
              <td class="waktu-log"><?= date('d M Y, H:i', strtotime($log['dicatat_pada'])) ?></td>
              <td><?= htmlspecialchars($log['nama_admin_saat_itu']) ?></td>
              <td>
                <span class="lencana-modul" style="background:<?= $m['warna'] ?>;color:<?= $m['teks_warna'] ?>;">
                  <?= htmlspecialchars($m['teks']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($log['keterangan']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($total_halaman > 1): ?>
        <div class="paginasi-log">
          <?php if ($halaman > 1): ?>
            <a href="<?= pertahankan_query_string(['halaman' => $halaman - 1]) ?>">&larr; Sebelumnya</a>
          <?php endif; ?>
          <span class="aktif">Halaman <?= $halaman ?> / <?= $total_halaman ?></span>
          <?php if ($halaman < $total_halaman): ?>
            <a href="<?= pertahankan_query_string(['halaman' => $halaman + 1]) ?>">Berikutnya &rarr;</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
</body>
</html>

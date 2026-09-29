<?php
/**
 * TOPBAR_ADMIN.PHP
 * Bilah atas panel admin. Set $judul_halaman_admin dan $subjudul_halaman_admin
 * sebelum include untuk menyesuaikan judul halaman yang tampil.
 */
$judul_halaman_admin = $judul_halaman_admin ?? 'Admin';
$subjudul_halaman_admin = $subjudul_halaman_admin ?? '';

$nama_admin_sesi = $_SESSION['nama_admin'] ?? 'Admin';
$peran_sesi = $_SESSION['peran'] ?? 'admin';
$label_peran = 'Admin';
$kata_nama = preg_split('/\s+/', trim($nama_admin_sesi));
$inisial_admin = strtoupper(($kata_nama[0][0] ?? 'A') . ($kata_nama[1][0] ?? ($kata_nama[0][1] ?? '')));
?>
<div class="topbar-admin">
  <div>
    <h1><?= htmlspecialchars($judul_halaman_admin) ?></h1>
    <?php if ($subjudul_halaman_admin): ?>
      <p class="subjudul-admin"><?= htmlspecialchars($subjudul_halaman_admin) ?></p>
    <?php endif; ?>
  </div>

  <div class="topbar-kanan">
    <form class="cari-admin" method="GET" action="<?= BASE_URL ?>/admin/kelola_pesanan.php">
      <span>🔍</span>
      <input type="text" name="cari" placeholder="Cari pesanan / pelanggan..." value="<?= htmlspecialchars($_GET['cari'] ?? '') ?>">
    </form>

    <div class="lonceng-admin" title="<?= $jumlah_menunggu_dp_notif ?> pesanan menunggu DP">
      🔔
      <?php if ($jumlah_menunggu_dp_notif > 0): ?>
        <span class="titik-notif"><?= $jumlah_menunggu_dp_notif ?></span>
      <?php endif; ?>
    </div>

    <div class="profil-admin">
      <div class="avatar-admin"><?= htmlspecialchars($inisial_admin) ?></div>
      <div>
        <div class="nama-admin-atas"><?= htmlspecialchars($nama_admin_sesi) ?></div>
        <div class="peran-admin-atas"><?= htmlspecialchars($label_peran) ?></div>
      </div>
    </div>
  </div>
</div>

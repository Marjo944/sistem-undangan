<?php
/**
 * SIDEBAR_ADMIN.PHP
 * Sidebar navigasi panel admin.
 * Set $halaman_admin_aktif sebelum include.
 * Nilai valid: 'dashboard','cari_pesan','desain','kategori','pengujian','pesanan','laporan_cnn','laporan_tfidf','log_aktivitas','sistem','pengaturan'
 */
$halaman_admin_aktif = $halaman_admin_aktif ?? '';

$jumlah_menunggu_dp_notif = 0;
try {
    $jumlah_menunggu_dp_notif = (int) $koneksi->query(
        "SELECT COUNT(*) AS jumlah FROM pesanan WHERE status = 'menunggu_dp'"
    )->fetch()['jumlah'];
} catch (Exception $e) { /* tabel belum ada — abaikan */ }
?>
<aside class="sidebar-admin">
  <a href="<?= BASE_URL ?>/admin/dashboard.php" class="merek-admin">
    <img src="<?= BASE_URL ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya" class="lambang-admin">
    <span>Rotama Jaya</span>
  </a>

  <ul class="nav-admin">
    <li><a href="<?= BASE_URL ?>/admin/dashboard.php"
        class="<?= $halaman_admin_aktif === 'dashboard' ? 'aktif' : '' ?>">
      <span class="ikon-nav">▦</span><span class="teks-nav">Dashboard</span>
    </a></li>

    <li><a href="<?= BASE_URL ?>/admin/cari_pesan.php"
        class="<?= $halaman_admin_aktif === 'cari_pesan' ? 'aktif' : '' ?>"
        style="<?= $halaman_admin_aktif !== 'cari_pesan' ? 'border-left:3px solid var(--warna-utama);' : '' ?>">
      <span class="ikon-nav">🔍</span><span class="teks-nav">Cari &amp; Pesan</span>
    </a></li>

    <li><a href="<?= BASE_URL ?>/admin/kelola_pesanan.php"
        class="<?= $halaman_admin_aktif === 'pesanan' ? 'aktif' : '' ?>">
      <span class="ikon-nav">🧾</span>
      <span class="teks-nav">
        Semua Pesanan
        <?php if ($jumlah_menunggu_dp_notif > 0): ?>
          <span style="background:var(--admin-merah);color:#fff;border-radius:20px;
            padding:1px 7px;font-size:11px;margin-left:4px;font-weight:700;">
            <?= $jumlah_menunggu_dp_notif ?>
          </span>
        <?php endif; ?>
      </span>
    </a></li>

    <li><a href="<?= BASE_URL ?>/admin/kelola_desain.php"
        class="<?= $halaman_admin_aktif === 'desain' ? 'aktif' : '' ?>">
      <span class="ikon-nav">🖼</span><span class="teks-nav">Data Desain</span>
    </a></li>

    <li><a href="<?= BASE_URL ?>/admin/kelola_kategori.php"
        class="<?= $halaman_admin_aktif === 'kategori' ? 'aktif' : '' ?>">
      <span class="ikon-nav">🏷</span><span class="teks-nav">Kategori</span>
    </a></li>

    <li><a href="<?= BASE_URL ?>/admin/pengujian.php"
        class="<?= $halaman_admin_aktif === 'pengujian' ? 'aktif' : '' ?>">
      <span class="ikon-nav">🧪</span><span class="teks-nav">Pengujian</span>
    </a></li>
  </ul>

  <div class="label-grup-admin">Lainnya</div>
  <ul class="nav-admin" style="padding-top:0;">
    <li><a href="<?= BASE_URL ?>/admin/log_aktivitas.php"
        class="<?= $halaman_admin_aktif === 'log_aktivitas' ? 'aktif' : '' ?>">
      <span class="ikon-nav">📜</span><span class="teks-nav">Log Aktivitas</span>
    </a></li>
    <li><a href="<?= BASE_URL ?>/admin/pengaturan.php"
        class="<?= $halaman_admin_aktif === 'pengaturan' ? 'aktif' : '' ?>">
      <span class="ikon-nav">⚙</span><span class="teks-nav">Pengaturan</span>
    </a></li>
  </ul>

  <div class="keluar-admin">
    <a href="<?= BASE_URL ?>/admin/keluar_admin.php">⏻ <span class="teks-nav">Keluar</span></a>
  </div>
</aside>

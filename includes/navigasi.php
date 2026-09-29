<?php
/**
 * NAVIGASI.PHP
 * Komponen navbar bersama untuk halaman yang dilihat admin saat
 * mendampingi pelanggan (beranda, katalog, tentang kami, pencarian).
 * Halaman-halaman ini mewajibkan sesi admin aktif (lihat periksa_sesi_admin()),
 * jadi navbar ini menampilkan nama admin yang sedang masuk beserta tombol Keluar.
 * Set variabel $halaman_aktif sebelum include untuk menandai menu yang sedang aktif.
 * Nilai yang valid: 'beranda', 'katalog', 'tentang'
 */
$halaman_aktif = $halaman_aktif ?? '';
?>
<nav class="bilah-navigasi">
  <a href="<?= BASE_URL ?>/beranda.php" class="merek">
    <img src="<?= BASE_URL ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya" class="lambang">
    <span><b>Rotama Jaya</b> Design</span>
  </a>

  <ul class="menu-navigasi">
    <li><a href="<?= BASE_URL ?>/beranda.php" class="<?= $halaman_aktif === 'beranda' ? 'aktif' : '' ?>">Beranda</a></li>
    <li><a href="<?= BASE_URL ?>/katalog.php" class="<?= $halaman_aktif === 'katalog' ? 'aktif' : '' ?>">Katalog</a></li>
    <li><a href="<?= BASE_URL ?>/tentang_kami.php" class="<?= $halaman_aktif === 'tentang' ? 'aktif' : '' ?>">Tentang Kami</a></li>
  </ul>

  <div style="display:flex;align-items:center;gap:14px;">
    <?php if (isset($_SESSION['id_admin'])): ?>
      <span style="font-size:13px;color:var(--warna-teks-sekunder);">
        👤 <?= htmlspecialchars($_SESSION['nama_admin'] ?? 'Admin') ?>
      </span>
      <a href="<?= BASE_URL ?>/beranda_publik.php" class="tombol-garis" style="font-size:13px;">
        🌐 Situs Publik
      </a>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="tombol-garis" style="font-size:13px;">
        📊 Dashboard
      </a>
      <a href="<?= BASE_URL ?>/admin/keluar_admin.php" class="tombol-garis" style="font-size:13px;">
        🚪 Keluar
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/admin/masuk_admin.php" class="tombol-garis" style="font-size:13px;">
        🔐 Masuk Admin
      </a>
    <?php endif; ?>
  </div>
</nav>

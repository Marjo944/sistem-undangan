<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$halaman_admin_aktif = 'pengaturan';
$judul_halaman_admin = 'Pengaturan Akun';
$subjudul_halaman_admin = 'Kelola nama dan kata sandi akun admin kamu';

$pesan = ''; $jenis_pesan = '';

$stmt = $koneksi->prepare("SELECT nama_admin, nama_pengguna, kata_sandi FROM admin WHERE id_admin = ?");
$stmt->execute([$_SESSION['id_admin']]);
$admin_saat_ini = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_admin = trim($_POST['nama_admin'] ?? '');
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $sandi_lama = $_POST['sandi_lama'] ?? '';
    $sandi_baru = $_POST['sandi_baru'] ?? '';

    if ($nama_admin === '' || $nama_pengguna === '') {
        $pesan = 'Nama dan nama pengguna tidak boleh kosong.'; $jenis_pesan = 'gagal';
    } elseif ($sandi_baru !== '' && $sandi_lama !== $admin_saat_ini['kata_sandi']) {
        $pesan = 'Kata sandi lama yang kamu masukkan salah.'; $jenis_pesan = 'gagal';
    } else {
        if ($sandi_baru !== '') {
            $koneksi->prepare("UPDATE admin SET nama_admin=?, nama_pengguna=?, kata_sandi=? WHERE id_admin=?")
                ->execute([$nama_admin, $nama_pengguna, $sandi_baru, $_SESSION['id_admin']]);
        } else {
            $koneksi->prepare("UPDATE admin SET nama_admin=?, nama_pengguna=? WHERE id_admin=?")
                ->execute([$nama_admin, $nama_pengguna, $_SESSION['id_admin']]);
        }
        $_SESSION['nama_admin'] = $nama_admin;

        // Keterangan log SENGAJA tidak menyertakan kata sandi apa pun.
        $keterangan = 'Memperbarui pengaturan akun sendiri (nama/nama pengguna'
            . ($sandi_baru !== '' ? ' + kata sandi' : '') . ')';
        catat_log_aktivitas($koneksi, 'pengaturan', 'ubah', $keterangan);

        $pesan = 'Pengaturan akun berhasil disimpan.'; $jenis_pesan = 'sukses';

        $stmt = $koneksi->prepare("SELECT nama_admin, nama_pengguna, kata_sandi FROM admin WHERE id_admin = ?");
        $stmt->execute([$_SESSION['id_admin']]);
        $admin_saat_ini = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
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

      <div class="panel-admin" style="max-width:520px;">
        <div class="judul-panel"><h3>Profil Admin</h3></div>
        <form method="POST" class="form-admin">
          <label>Nama Lengkap</label>
          <input type="text" name="nama_admin" value="<?= htmlspecialchars($admin_saat_ini['nama_admin']) ?>" required>

          <label>Nama Pengguna (untuk login)</label>
          <input type="text" name="nama_pengguna" value="<?= htmlspecialchars($admin_saat_ini['nama_pengguna']) ?>" required>

          <hr style="margin:20px 0;border:none;border-top:1px solid var(--warna-batas);">
          <p style="font-size:13px;color:var(--warna-teks-sekunder);margin:0 0 4px;">
            Kosongkan bagian di bawah ini jika tidak ingin mengganti kata sandi.
          </p>

          <label>Kata Sandi Lama</label>
          <input type="password" name="sandi_lama" placeholder="Isi hanya jika ingin ganti kata sandi">

          <label>Kata Sandi Baru</label>
          <input type="password" name="sandi_baru" placeholder="Kata sandi baru">

          <button type="submit" class="tombol-utama" style="margin-top:20px;width:100%;">Simpan Perubahan</button>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>

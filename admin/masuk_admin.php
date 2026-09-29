<?php
require_once __DIR__ . '/../koneksi_database.php';

$pesan_kesalahan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $kata_sandi = $_POST['kata_sandi'] ?? '';

    $pernyataan = $koneksi->prepare("SELECT * FROM admin WHERE nama_pengguna = ?");
    $pernyataan->execute([$nama_pengguna]);
    $data_admin = $pernyataan->fetch();

    // Pencocokan kata sandi langsung (teks biasa) sesuai ketentuan sistem
    if ($data_admin && $kata_sandi === $data_admin['kata_sandi']) {
        $_SESSION['id_admin'] = $data_admin['id_admin'];
        $_SESSION['nama_admin'] = $data_admin['nama_admin'];
        $_SESSION['peran'] = $data_admin['peran'] ?? 'admin';

        catat_log_aktivitas($koneksi, 'auth', 'login', "Login berhasil sebagai \"{$data_admin['nama_admin']}\"");

        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    } else {
        $pesan_kesalahan = 'Nama pengguna atau kata sandi salah.';
        // Dicatat tanpa id_admin (login gagal = belum tentu tahu identitas penyerang),
        // nama pengguna yang dicoba tetap direkam untuk mendeteksi percobaan brute-force.
        catat_log_aktivitas($koneksi, 'auth', 'login_gagal', "Percobaan login gagal untuk nama pengguna \"{$nama_pengguna}\"");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk Admin - Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">

  <div class="logo-rotama">
    <img src="<?= BASE_URL ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya">
    <span>Rotama Jaya</span>
  </div>

  <div class="kotak-login" style="width:100%;max-width:380px;">
    <h2 style="text-align:center;color:var(--warna-utama);margin-bottom:4px;">Masuk Admin</h2>
    <p style="text-align:center;color:var(--warna-teks-sekunder);font-size:14px;margin-bottom:24px;">
      Akses khusus pengelola sistem
    </p>

    <?php if ($pesan_kesalahan): ?>
      <div style="background:#fef2f2;color:#b91c1c;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:14px;">
        <?= htmlspecialchars($pesan_kesalahan) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <label style="font-size:14px;color:var(--warna-teks-sekunder);">Nama Pengguna</label>
      <input type="text" name="nama_pengguna" required
        style="width:100%;padding:12px;margin:6px 0 16px;border-radius:10px;border:1px solid var(--warna-batas);">

      <label style="font-size:14px;color:var(--warna-teks-sekunder);">Kata Sandi</label>
      <input type="password" name="kata_sandi" required
        style="width:100%;padding:12px;margin:6px 0 24px;border-radius:10px;border:1px solid var(--warna-batas);">

      <button type="submit" class="tombol-utama" style="width:100%;">Masuk</button>
    </form>
  </div>

</body>
</html>

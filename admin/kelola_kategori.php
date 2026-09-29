<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$halaman_admin_aktif = 'kategori';
$judul_halaman_admin = 'Kelola Kategori';
$subjudul_halaman_admin = 'Atur kategori desain undangan (Pernikahan, Ulang Tahun, Syukuran, dll)';

$pesan = '';
$jenis_pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_kategori'])) {
        $nama = trim($_POST['nama_kategori'] ?? '');
        if ($nama === '') {
            $pesan = 'Nama kategori tidak boleh kosong.'; $jenis_pesan = 'gagal';
        } else {
            $cek = $koneksi->prepare("SELECT COUNT(*) c FROM kategori_desain WHERE nama_kategori = ?");
            $cek->execute([$nama]);
            if ($cek->fetch()['c'] > 0) {
                $pesan = 'Kategori tersebut sudah ada.'; $jenis_pesan = 'gagal';
            } else {
                $koneksi->prepare("INSERT INTO kategori_desain (nama_kategori) VALUES (?)")->execute([$nama]);
                catat_log_aktivitas($koneksi, 'kategori', 'tambah', "Menambahkan kategori \"{$nama}\"");
                $pesan = 'Kategori "' . $nama . '" berhasil ditambahkan.'; $jenis_pesan = 'sukses';
            }
        }
    } elseif (isset($_POST['hapus_kategori'])) {
        $id = (int) $_POST['id_kategori'];
        $dipakai = $koneksi->prepare("SELECT COUNT(*) c FROM desain_undangan WHERE id_kategori = ?");
        $dipakai->execute([$id]);
        if ($dipakai->fetch()['c'] > 0) {
            $pesan = 'Kategori tidak bisa dihapus karena masih dipakai oleh desain yang ada.';
            $jenis_pesan = 'gagal';
        } else {
            $nama_kategori_dihapus = $koneksi->prepare("SELECT nama_kategori FROM kategori_desain WHERE id_kategori = ?");
            $nama_kategori_dihapus->execute([$id]);
            $nama_kategori_dihapus = $nama_kategori_dihapus->fetchColumn();

            $koneksi->prepare("DELETE FROM kategori_desain WHERE id_kategori = ?")->execute([$id]);
            catat_log_aktivitas($koneksi, 'kategori', 'hapus', "Menghapus kategori \"{$nama_kategori_dihapus}\"");
            $pesan = 'Kategori berhasil dihapus.'; $jenis_pesan = 'sukses';
        }
    }
}

$daftar_kategori = $koneksi->query(
    "SELECT k.id_kategori, k.nama_kategori, COUNT(d.id_desain) AS jumlah_desain
     FROM kategori_desain k
     LEFT JOIN desain_undangan d ON d.id_kategori = k.id_kategori
     GROUP BY k.id_kategori, k.nama_kategori
     ORDER BY k.nama_kategori"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kategori - Rotama Jaya</title>
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

      <div class="grid-dasbor" style="grid-template-columns: 1fr 1.6fr;">
        <div class="panel-admin">
          <div class="judul-panel"><h3>Tambah Kategori</h3></div>
          <form method="POST" class="form-admin">
            <label>Nama Kategori</label>
            <input type="text" name="nama_kategori" placeholder="Contoh: Aqiqah" required>
            <button type="submit" name="tambah_kategori" value="1" class="tombol-utama" style="margin-top:16px;width:100%;">
              + Tambah Kategori
            </button>
          </form>
        </div>

        <div class="panel-admin">
          <div class="judul-panel"><h3>Daftar Kategori</h3></div>
          <?php if (empty($daftar_kategori)): ?>
            <p style="color:var(--warna-teks-sekunder);font-size:14px;">Belum ada kategori.</p>
          <?php else: ?>
          <table class="tabel-admin">
            <thead><tr><th>Nama Kategori</th><th>Jumlah Desain</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($daftar_kategori as $k): ?>
              <tr>
                <td><?= htmlspecialchars($k['nama_kategori']) ?></td>
                <td><?= (int) $k['jumlah_desain'] ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Hapus kategori ini?');" style="display:inline;">
                    <input type="hidden" name="id_kategori" value="<?= $k['id_kategori'] ?>">
                    <button type="submit" name="hapus_kategori" value="1"
                      style="border:none;background:none;color:var(--admin-merah);font-weight:700;cursor:pointer;font-size:13px;">
                      Hapus
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

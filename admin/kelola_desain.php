<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$pesan = '';
$jenis_pesan = 'sukses';

function redirect_ke_kelola_desain(string $status): void
{
    header('Location: ' . BASE_URL . '/admin/kelola_desain.php?status=' . rawurlencode($status));
    exit;
}

function jalankan_proses_desain_baru_terpisah(int $id_desain, string $judul, string $kata_kunci, string $nama_file_gambar, ?string $nama_file_produksi, int $id_kategori, ?string $tema): void
{
    $payload = [
        'id_desain' => $id_desain,
        'judul' => $judul,
        'kata_kunci' => $kata_kunci,
        'nama_file_gambar' => $nama_file_gambar,
        'nama_file_produksi' => $nama_file_produksi,
        'id_kategori' => $id_kategori,
        'tema' => $tema,
    ];

    $script = __DIR__ . '/../scripts/proses_desain_baru.php';
    // PHP-FPM dapat melaporkan PHP_BINARY sebagai php-fpm, bukan PHP CLI.
    // Gunakan PHP_CLI_BIN agar job background benar-benar dapat dijalankan di VPS.
    $php_bin = konfigurasi('PHP_CLI_BIN', 'php');
    $perintah = escapeshellarg($php_bin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen('start /B "" ' . $perintah, 'r'));
        return;
    }

    @exec($perintah . ' > /dev/null 2>&1 &');
}

$ekstensi_gambar_diizinkan = ['jpg', 'jpeg', 'png', 'webp'];
$ekstensi_produksi_diizinkan = ['psd', 'cdr', 'ai', 'pdf', 'zip', 'rar'];

/**
 * Menerjemahkan kode error unggahan PHP (UPLOAD_ERR_*) jadi pesan yang jelas
 * bagi admin.
 *
 * PENTING: kalau ukuran file melebihi batas server (php.ini upload_max_filesize
 * / post_max_size) — dan file produksi PSD/CDR di percetakan biasanya berukuran
 * ratusan MB — PHP TIDAK menyimpan file-nya sama sekali, walau field 'name'
 * tetap terisi nama file aslinya. Tanpa mengecek 'error' ini, sistem bisa
 * diam-diam menyimpan NAMA file ke database padahal file-nya sendiri gagal
 * tersimpan di server — baru ketahuan nanti saat admin/pelanggan mau
 * mengunduhnya lewat unduh_produksi.php.
 */
function pesan_error_unggahan(int $kode): string
{
    return match ($kode) {
        UPLOAD_ERR_OK => '',
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'Ukuran file melebihi batas maksimum unggahan server saat ini (upload_max_filesize / post_max_size di php.ini). Hubungi administrator server untuk menaikkan batas ini — lihat PANDUAN_HOSTING_VPS.md.',
        UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian (koneksi terputus di tengah proses). Coba unggah ulang.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
            'Server gagal menyimpan file yang diunggah (masalah konfigurasi server). Hubungi administrator server.',
        default => 'Terjadi kesalahan saat mengunggah file (kode: ' . $kode . ').',
    };
}

// ---- Unggah desain BARU ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unggah_desain'])) {
    $judul = trim($_POST['judul_desain']);
    $deskripsi = trim($_POST['deskripsi_desain']);
    $kata_kunci = trim($_POST['kata_kunci']);
    $id_kategori = $_POST['id_kategori'];
    $tema = $_POST['tema'] !== '' ? $_POST['tema'] : null;

    // Batasi ekstensi yang boleh diunggah, supaya folder unggahan (yang bisa
    // diakses lewat URL publik untuk gambar) tidak bisa disusupi file script
    // seperti .php/.phtml oleh siapa pun yang berhasil mendapat akses admin.
    $error_gambar = $_FILES['file_gambar']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error_produksi = $_FILES['file_produksi']['error'] ?? UPLOAD_ERR_NO_FILE;

    $ekstensi_gambar = $error_gambar === UPLOAD_ERR_OK
        ? strtolower(pathinfo($_FILES['file_gambar']['name'], PATHINFO_EXTENSION))
        : null;
    $ekstensi_produksi = $error_produksi === UPLOAD_ERR_OK
        ? strtolower(pathinfo($_FILES['file_produksi']['name'], PATHINFO_EXTENSION))
        : null;

    if ($error_gambar !== UPLOAD_ERR_OK) {
        $pesan = '⚠️ Gagal mengunggah gambar desain. ' . pesan_error_unggahan($error_gambar);
        $jenis_pesan = 'gagal';
    } elseif ($error_produksi !== UPLOAD_ERR_OK && $error_produksi !== UPLOAD_ERR_NO_FILE) {
        $pesan = '⚠️ Gagal mengunggah file produksi. ' . pesan_error_unggahan($error_produksi);
        $jenis_pesan = 'gagal';
    } elseif ($id_kategori === '' || $id_kategori === null) {
        $pesan = '⚠️ Kategori belum dipilih. Buat kategori terlebih dahulu lewat menu "Kelola Kategori" sebelum mengunggah desain.';
        $jenis_pesan = 'gagal';
    } elseif (!in_array($ekstensi_gambar, $ekstensi_gambar_diizinkan, true)) {
        $pesan = '⚠️ Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.';
        $jenis_pesan = 'gagal';
    } elseif ($ekstensi_produksi !== null && !in_array($ekstensi_produksi, $ekstensi_produksi_diizinkan, true)) {
        $pesan = '⚠️ Format file produksi tidak didukung. Gunakan PSD, CDR, AI, PDF, ZIP, atau RAR.';
        $jenis_pesan = 'gagal';
    } else {
        $nama_file_gambar = uniqid('desain_') . '.' . $ekstensi_gambar;
        if (!move_uploaded_file($_FILES['file_gambar']['tmp_name'], DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar)) {
          $pesan = '⚠️ Server tidak dapat menyimpan gambar desain. Periksa permission folder uploads/desain.';
          $jenis_pesan = 'gagal';
        }

        $nama_file_produksi = null;
        if ($jenis_pesan === 'sukses' && $ekstensi_produksi !== null) {
            $nama_file_produksi = uniqid('produksi_') . '.' . $ekstensi_produksi;
          if (!move_uploaded_file($_FILES['file_produksi']['tmp_name'], DIREKTORI_FILE_PRODUKSI . $nama_file_produksi)) {
            @unlink(DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar);
            $pesan = '⚠️ Server tidak dapat menyimpan file produksi. Periksa permission folder uploads/produksi.';
            $jenis_pesan = 'gagal';
          }
        }

        if ($jenis_pesan !== 'sukses') {
          // Lewati INSERT agar database tidak memiliki metadata tanpa file fisik.
        } else {
        // Proses fitur CNN/TF-IDF tidak lagi dijalankan synchronously di request utama.
        // Agar upload terasa ringan di online, kita langsung simpan metadata desain dan
        // menjalankan pemrosesan di background lewat script terpisah. Ini menjaga logika
        // model CNN/TF-IDF tetap sama, hanya eksekusinya yang dipindah ke background job.
        $pernyataan = $koneksi->prepare(
            "INSERT INTO desain_undangan
            (id_kategori, tema, judul_desain, deskripsi_desain, kata_kunci, nama_file_gambar, nama_file_webp, nama_file_produksi, status_proses,
             vektor_fitur_cnn, vektor_fitur_tfidf, diunggah_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $pernyataan->execute([
            $id_kategori, $tema, $judul, $deskripsi, $kata_kunci, $nama_file_gambar, null, $nama_file_produksi, 'diproses',
            null, null, $_SESSION['id_admin'],
        ]);

        $id_desain_baru = (int) $koneksi->lastInsertId();
        jalankan_proses_desain_baru_terpisah($id_desain_baru, $judul, $kata_kunci, $nama_file_gambar, $nama_file_produksi, $id_kategori, $tema);

        catat_log_aktivitas($koneksi, 'desain', 'tambah', "Mengunggah desain baru \"{$judul}\" dan memulai pemrosesan fitur di background");

        redirect_ke_kelola_desain('unggah_diproses');
        }
    }
}

// ---- Perbarui desain YANG SUDAH ADA (Edit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['perbarui_desain'])) {
    $id_desain = (int) $_POST['id_desain'];
    $judul = trim($_POST['judul_desain']);
    $deskripsi = trim($_POST['deskripsi_desain']);
    $kata_kunci = trim($_POST['kata_kunci']);
    $id_kategori = $_POST['id_kategori'];
    $tema = $_POST['tema'] !== '' ? $_POST['tema'] : null;

    $stmt = $koneksi->prepare(
        "SELECT id_desain, kata_kunci, nama_file_gambar, nama_file_webp, nama_file_produksi, vektor_fitur_cnn
         FROM desain_undangan WHERE id_desain = ?"
    );
    $stmt->execute([$id_desain]);
    $desain_lama = $stmt->fetch();

    if (!$desain_lama) {
        $pesan = '⚠️ Desain tidak ditemukan.';
        $jenis_pesan = 'gagal';
    } elseif ($id_kategori === '' || $id_kategori === null) {
        $pesan = '⚠️ Kategori belum dipilih. Buat kategori terlebih dahulu lewat menu "Kelola Kategori".';
        $jenis_pesan = 'gagal';
    } else {
        $nama_file_gambar = $desain_lama['nama_file_gambar'];
        $nama_file_produksi = $desain_lama['nama_file_produksi'];
        $vektor_fitur_cnn = $desain_lama['vektor_fitur_cnn'];
        $gagal = false;

        // Gambar pratinjau baru diunggah? (opsional saat edit)
        $error_gambar_baru = $_FILES['file_gambar']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error_gambar_baru !== UPLOAD_ERR_NO_FILE) {
            if ($error_gambar_baru !== UPLOAD_ERR_OK) {
                $pesan = '⚠️ Gagal mengunggah gambar baru. ' . pesan_error_unggahan($error_gambar_baru);
                $jenis_pesan = 'gagal';
                $gagal = true;
            } else {
            $ekstensi_gambar = strtolower(pathinfo($_FILES['file_gambar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi_gambar, $ekstensi_gambar_diizinkan, true)) {
                $pesan = '⚠️ Format gambar tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.';
                $jenis_pesan = 'gagal';
                $gagal = true;
            } else {
                $nama_file_gambar_baru = uniqid('desain_') . '.' . $ekstensi_gambar;
                move_uploaded_file($_FILES['file_gambar']['tmp_name'], DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar_baru);

                // Hitung ulang HANYA vektor CNN untuk gambar baru ini
                $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/hitung_vektor_gambar_tunggal');
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, ['gambar' => new CURLFile(DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar_baru)]);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 3000);
                curl_setopt($curl, CURLOPT_TIMEOUT_MS, 30000); // dilonggarkan dari 8000ms: cukup untuk ekstraksi CNN + latih ulang TF-IDF walau korpus membesar
                $tanggapan = curl_exec($curl);
                curl_close($curl);

                if ($tanggapan === false) {
                    $pesan = '⚠️ Gagal terhubung ke layanan Python untuk menghitung ulang vektor gambar. Pastikan aplikasi_layanan.py sedang berjalan.';
                    $jenis_pesan = 'gagal';
                    $gagal = true;
                    @unlink(DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar_baru);
                } else {
                    $data_gambar = json_decode($tanggapan, true);
                    $vektor_fitur_cnn = json_encode($data_gambar['vektor_cnn'] ?? []);
                    $nama_file_webp_baru = $data_gambar['nama_file_webp'] ?? null;
                    // Hapus file gambar lama, pakai yang baru
                    @unlink(DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar);
                    if (!empty($desain_lama['nama_file_webp'])) {
                        @unlink(DIREKTORI_GAMBAR_DESAIN . $desain_lama['nama_file_webp']);
                    }
                    $nama_file_gambar = $nama_file_gambar_baru;
                    $nama_file_webp = $nama_file_webp_baru;
                }
            }
            }
        }

        // File produksi baru diunggah? (opsional saat edit)
        $error_produksi_baru = $_FILES['file_produksi']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!$gagal && $error_produksi_baru !== UPLOAD_ERR_NO_FILE) {
            if ($error_produksi_baru !== UPLOAD_ERR_OK) {
                $pesan = '⚠️ Gagal mengunggah file produksi baru. ' . pesan_error_unggahan($error_produksi_baru);
                $jenis_pesan = 'gagal';
                $gagal = true;
            } else {
            $ekstensi_produksi = strtolower(pathinfo($_FILES['file_produksi']['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi_produksi, $ekstensi_produksi_diizinkan, true)) {
                $pesan = '⚠️ Format file produksi tidak didukung. Gunakan PSD, CDR, AI, PDF, ZIP, atau RAR.';
                $jenis_pesan = 'gagal';
                $gagal = true;
            } else {
                $nama_file_produksi_baru = uniqid('produksi_') . '.' . $ekstensi_produksi;
                move_uploaded_file($_FILES['file_produksi']['tmp_name'], DIREKTORI_FILE_PRODUKSI . $nama_file_produksi_baru);
                if ($nama_file_produksi) {
                    @unlink(DIREKTORI_FILE_PRODUKSI . $nama_file_produksi);
                }
                $nama_file_produksi = $nama_file_produksi_baru;
            }
            }
        }

        if (!$gagal) {
            $koneksi->prepare(
                "UPDATE desain_undangan
                 SET id_kategori = ?, tema = ?, judul_desain = ?, deskripsi_desain = ?, kata_kunci = ?,
                     nama_file_gambar = ?, nama_file_webp = ?, nama_file_produksi = ?, vektor_fitur_cnn = ?
                 WHERE id_desain = ?"
            )->execute([
                $id_kategori, $tema, $judul, $deskripsi, $kata_kunci,
                $nama_file_gambar, $nama_file_webp ?? null, $nama_file_produksi, $vektor_fitur_cnn, $id_desain,
            ]);

            catat_log_aktivitas($koneksi, 'desain', 'ubah', "Mengubah data desain #{$id_desain} \"{$judul}\"");

            // Kata kunci berubah? Kosakata TF-IDF ikut berubah, jadi vektor SEMUA
            // desain (bukan hanya yang ini) perlu dilatih ulang supaya tetap adil
            // dibandingkan satu sama lain — sama seperti saat ada desain baru diunggah.
            // PENTING: cek & jalankan ini SEBELUM redirect, karena redirect_ke_kelola_desain()
            // langsung header()+exit — kalau dipanggil duluan, kode di bawahnya tidak akan
            // pernah tereksekusi sama sekali.
            $gagal_latih_ulang = false;
            if ($kata_kunci !== (string) $desain_lama['kata_kunci']) {
                $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/latih_ulang_tfidf_semua');
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 3000);
                curl_setopt($curl, CURLOPT_TIMEOUT_MS, 30000); // dilonggarkan dari 8000ms: cukup untuk ekstraksi CNN + latih ulang TF-IDF walau korpus membesar
                $tanggapan = curl_exec($curl);
                curl_close($curl);

                if ($tanggapan === false) {
                    $pesan = '⚠️ Desain berhasil diperbarui. Namun kata kunci berubah dan layanan Python tidak dapat dihubungi untuk melatih ulang TF-IDF seluruh desain. Tunggu layanan Python aktif lagi, lalu ulangi proses penyimpanan atau jalankan endpoint pemutakhiran TF-IDF dari sistem.';
                    $jenis_pesan = 'gagal';
                    $gagal_latih_ulang = true;
                }
            }

            // Hanya redirect (pola PRG) kalau tidak ada pesan peringatan yang perlu
            // ditampilkan ke admin; kalau tidak, biarkan halaman ini merender $pesan di atas.
            if (!$gagal_latih_ulang) {
                redirect_ke_kelola_desain('edit_sukses');
            }
        }
    }
}

// ---- Hapus desain ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_desain'])) {
    $id_desain = (int) $_POST['id_desain'];
    $stmt = $koneksi->prepare("SELECT judul_desain, nama_file_gambar, nama_file_webp, nama_file_produksi FROM desain_undangan WHERE id_desain = ?");
    $stmt->execute([$id_desain]);
    $desain_dihapus = $stmt->fetch();

    if (!$desain_dihapus) {
        $pesan = '⚠️ Desain tidak ditemukan.';
        $jenis_pesan = 'gagal';
    } else {
        $hapus = $koneksi->prepare("DELETE FROM desain_undangan WHERE id_desain = ?");
        $hapus->execute([$id_desain]);

        if ($hapus->rowCount() > 0) {
            @unlink(DIREKTORI_GAMBAR_DESAIN . $desain_dihapus['nama_file_gambar']);
            if (!empty($desain_dihapus['nama_file_webp'])) {
                @unlink(DIREKTORI_GAMBAR_DESAIN . $desain_dihapus['nama_file_webp']);
            }
            if (!empty($desain_dihapus['nama_file_produksi'])) {
                @unlink(DIREKTORI_FILE_PRODUKSI . $desain_dihapus['nama_file_produksi']);
            }
            catat_log_aktivitas($koneksi, 'desain', 'hapus', "Menghapus desain #{$id_desain} \"{$desain_dihapus['judul_desain']}\"");
            redirect_ke_kelola_desain('hapus_sukses');
        } else {
            $pesan = '⚠️ Gagal menghapus desain. Coba lagi.';
            $jenis_pesan = 'gagal';
        }
    }
}

if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'unggah_diproses':
            $pesan = 'Desain berhasil diunggah. Proses CNN/TF-IDF sedang berjalan di background dan akan selesai otomatis.';
            $jenis_pesan = 'sukses';
            break;
        case 'edit_sukses':
            $pesan = 'Desain berhasil diperbarui.';
            $jenis_pesan = 'sukses';
            break;
        case 'hapus_sukses':
            $pesan = 'Desain berhasil dihapus.';
            $jenis_pesan = 'sukses';
            break;
    }
}

$daftar_kategori = $koneksi->query(
    "SELECT id_kategori, nama_kategori FROM kategori_desain ORDER BY nama_kategori"
)->fetchAll();
$daftar_desain = $koneksi->query(
    "SELECT id_desain, judul_desain, tema, nama_file_gambar, nama_file_webp, nama_file_produksi, status_proses
     FROM desain_undangan ORDER BY dibuat_pada DESC"
)->fetchAll();

// ---- Mode edit: kalau ada ?edit=<id>, form di atas dipakai ulang untuk edit ----
$desain_diedit = null;
if (isset($_GET['edit'])) {
    $stmt = $koneksi->prepare(
        "SELECT id_desain, id_kategori, tema, judul_desain, deskripsi_desain, kata_kunci, nama_file_gambar
         FROM desain_undangan WHERE id_desain = ?"
    );
    $stmt->execute([(int) $_GET['edit']]);
    $desain_diedit = $stmt->fetch();
}

$halaman_admin_aktif = 'desain';
$judul_halaman_admin = 'Kelola Desain Undangan';
$subjudul_halaman_admin = 'Unggah desain baru dan lihat koleksi yang tersimpan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Desain - Admin Rotama Jaya</title>
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

  <?php if ($desain_diedit): ?>
    <h2 style="color:var(--warna-teks-utama);margin-bottom:6px;">✏️ Edit Desain: <?= htmlspecialchars($desain_diedit['judul_desain']) ?></h2>
    <p style="margin-top:0;"><a href="<?= BASE_URL ?>/admin/kelola_desain.php" style="font-size:13px;color:var(--warna-teks-sekunder);">&larr; Batal, kembali ke form unggah desain baru</a></p>
  <?php endif; ?>

  <form id="form_unggah_desain" method="POST" enctype="multipart/form-data" class="kartu-melayang" style="padding:24px;margin-bottom:32px;" onsubmit="return handleUploadSubmit(this)">
    <?php if ($desain_diedit): ?>
      <input type="hidden" name="perbarui_desain" value="1">
      <input type="hidden" name="id_desain" value="<?= $desain_diedit['id_desain'] ?>">
    <?php else: ?>
      <input type="hidden" name="unggah_desain" value="1">
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div>
        <label>Judul Desain</label>
        <input type="text" name="judul_desain" required value="<?= $desain_diedit ? htmlspecialchars($desain_diedit['judul_desain']) : '' ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--warna-batas);">
      </div>
      <div>
        <label>Kategori</label>
        <select name="id_kategori" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--warna-batas);">
          <?php foreach ($daftar_kategori as $k): ?>
            <option value="<?= $k['id_kategori'] ?>" <?= ($desain_diedit && (int)$desain_diedit['id_kategori'] === (int)$k['id_kategori']) ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label style="display:block;margin-top:14px;">Deskripsi</label>
    <textarea name="deskripsi_desain" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--warna-batas);"><?= $desain_diedit ? htmlspecialchars($desain_diedit['deskripsi_desain']) : '' ?></textarea>

    <label style="display:block;margin-top:14px;">Kata Kunci (untuk TF-IDF)</label>
    <input type="text" name="kata_kunci" placeholder="pisahkan dengan spasi/koma" value="<?= $desain_diedit ? htmlspecialchars($desain_diedit['kata_kunci']) : '' ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--warna-batas);">
    <?php if ($desain_diedit): ?>
      <p style="font-size:12px;color:var(--warna-teks-sekunder);margin:6px 0 0;">⚠️ Kalau kata kunci diubah, vektor TF-IDF seluruh desain (bukan cuma ini) otomatis dilatih ulang saat disimpan.</p>
    <?php endif; ?>

    <label style="display:block;margin-top:14px;">Tema</label>
    <select name="tema" style="width:100%;padding:10px;border-radius:8px;border:1px solid var(--warna-batas);">
      <option value="">-- Pilih Tema --</option>
      <?php foreach (daftar_tema_desain() as $nilai => $label): ?>
        <option value="<?= $nilai ?>" <?= ($desain_diedit && $desain_diedit['tema'] === $nilai) ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:14px;">
      <div>
        <label>Gambar Pratinjau (publik)<?= $desain_diedit ? ' — kosongkan jika tidak diganti' : '' ?></label>
        <?php if ($desain_diedit): ?>
          <img src="<?= htmlspecialchars(url_gambar_desain($desain_diedit['nama_file_gambar'], $desain_diedit['nama_file_webp'] ?? null)) ?>" style="width:80px;border-radius:8px;display:block;margin-bottom:6px;" loading="lazy" decoding="async">
        <?php endif; ?>
        <input type="file" name="file_gambar" accept="image/*" <?= $desain_diedit ? '' : 'required' ?> style="width:100%;">
      </div>
      <div>
        <label>File Produksi PSD/CDR (khusus admin)<?= $desain_diedit ? ' — kosongkan jika tidak diganti' : '' ?></label>
        <input type="file" name="file_produksi" style="width:100%;">
      </div>
    </div>

    <button type="submit" id="tombol_submit_desain" class="tombol-utama" style="margin-top:20px;"><?= $desain_diedit ? 'Simpan Perubahan' : 'Unggah Desain' ?></button>
  </form>

  <h2 style="color:var(--warna-teks-utama);">Daftar Desain</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px;">
    <?php foreach ($daftar_desain as $d): ?>
      <div class="kartu-melayang" style="padding:14px;">
        <img src="<?= htmlspecialchars(url_gambar_desain($d['nama_file_gambar'], $d['nama_file_webp'] ?? null)) ?>" style="width:100%;border-radius:10px;" loading="lazy" decoding="async">
        <h4 style="margin:10px 0 4px;"><?= htmlspecialchars($d['judul_desain']) ?></h4>
        <p style="margin:0 0 6px;font-size:13px;color:var(--warna-teks-sekunder);">
          <?= $d['tema'] ? htmlspecialchars(ucwords(str_replace('_', ' ', $d['tema']))) : '' ?>
        </p>
        <?php $status_proses = $d['status_proses'] ?? 'siap'; ?>
        <div style="margin:0 0 10px;padding:6px 8px;border-radius:999px;display:inline-block;font-size:11px;font-weight:700;letter-spacing:0.02em;
          <?php if ($status_proses === 'diproses') echo 'background:#fff4d6;color:#8a6500;'; elseif ($status_proses === 'gagal') echo 'background:#ffe1e1;color:#a52d2d;'; else echo 'background:#dff7ea;color:#1d6f45;'; ?>">
          <?php if ($status_proses === 'diproses') echo 'Sedang diproses'; elseif ($status_proses === 'gagal') echo 'Gagal diproses'; else echo 'Siap dipakai'; ?>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <a href="<?= BASE_URL ?>/admin/kelola_desain.php?edit=<?= $d['id_desain'] ?>" style="font-size:13px;color:var(--warna-utama);">✏️ Edit</a>
          <?php if ($d['nama_file_produksi']): ?>
            <a href="<?= BASE_URL ?>/admin/unduh_produksi.php?id=<?= $d['id_desain'] ?>" style="font-size:13px;color:var(--warna-utama);">⬇ Unduh File Produksi</a>
          <?php endif; ?>
          <!-- BEGIN HAPUS_DESAIN_BUTTON -->
          <form method="POST" onsubmit="return confirm('Hapus desain ini?');" style="display:inline;">
            <input type="hidden" name="hapus_desain" value="1">
            <input type="hidden" name="id_desain" value="<?= $d['id_desain'] ?>">
            <button type="submit" class="tombol-sekunder aksi-hapus-desain" style="font-size:13px;padding:6px 10px;border:1px solid rgba(224,65,54,0.2);border-radius:8px;background:transparent;color:#c0392b;cursor:pointer;">🗑️ Hapus</button>
          </form>
          <!-- END HAPUS_DESAIN_BUTTON -->
        </div>
      </div>
    <?php endforeach; ?>
  </div>

    </div>
  </div>
</div>
<script>
  function handleUploadSubmit(form) {
    var tombol = form.querySelector('button[type="submit"]');
    if (!tombol) {
      return true;
    }
    if (tombol.disabled) {
      return false;
    }
    tombol.disabled = true;
    tombol.textContent = 'Sedang mengunggah…';
    return true;
  }
</script>
</body>
</html>

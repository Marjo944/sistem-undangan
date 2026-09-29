<?php
/**
 * CARI_PESAN.PHP
 * Halaman utama alur kerja admin saat melayani pelanggan di toko:
 *  1. Admin cari desain via CNN (gambar referensi pelanggan) atau TF-IDF (kata kunci).
 *  2. Hasil rekomendasi tampil sebagai kartu — admin klik kartu untuk memilih desain.
 *     teks personalisasi + foto pelanggan + detail DP, lalu simpan pesanan.
 *
 * AJAX: endpoint /admin/cari_pesan.php?aksi=cari_teks  (POST JSON)
 *       endpoint /admin/cari_pesan.php?aksi=cari_gambar (POST multipart)
 */
require_once __DIR__ . '/../koneksi_database.php';
require_once __DIR__ . '/../includes/rekomendasi_helper.php';
periksa_sesi_admin();

/* ================================================================
   AJAX — cari teks (TF-IDF)
   ================================================================ */
if (($_GET['aksi'] ?? '') === 'cari_teks') {
    header('Content-Type: application/json');
    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $kata_kunci  = trim($body['kata_kunci'] ?? '');
    if ($kata_kunci === '') {
        echo json_encode(['kesalahan' => 'Kata kunci kosong.']);
        exit;
    }
    $data = panggil_layanan_teks($kata_kunci);
    if ($data === null) {
        echo json_encode(['kesalahan' => 'Layanan Python tidak dapat dihubungi. Pastikan aplikasi_layanan.py berjalan di ' . ALAMAT_LAYANAN_PYTHON . '.']);
        exit;
    }
    $hasil = perkaya_hasil_rekomendasi($koneksi, $data['hasil'] ?? []);

    /* Catat ke riwayat_pencarian */
    try {
        $koneksi->prepare(
            "INSERT INTO riwayat_pencarian (id_admin, jenis_pencarian, kata_kunci_dicari, waktu_proses_ms)
             VALUES (?, 'teks', ?, ?)"
        )->execute([$_SESSION['id_admin'], $kata_kunci, $data['waktu_proses_ms'] ?? null]);
        $id_riwayat = (int) $koneksi->lastInsertId();
        $jumlah_per_kategori = [];
        foreach ($hasil as $item) {
          $id_kategori = (int) ($item['id_kategori'] ?? 0);
          if ($id_kategori > 0) $jumlah_per_kategori[$id_kategori] = ($jumlah_per_kategori[$id_kategori] ?? 0) + 1;
        }
        $simpan_kategori = $koneksi->prepare(
          "INSERT INTO riwayat_pencarian_kategori (id_riwayat, id_kategori, jumlah_hasil) VALUES (?, ?, ?)"
        );
        foreach ($jumlah_per_kategori as $id_kategori => $jumlah) {
          $simpan_kategori->execute([$id_riwayat, $id_kategori, $jumlah]);
        }
    } catch (Exception $e) { /* tabel mungkin belum di-migrasi — abaikan */ }

    echo json_encode(['hasil' => $hasil, 'waktu_ms' => $data['waktu_proses_ms'] ?? null]);
    exit;
}

/* ================================================================
   AJAX — cari gambar (CNN)
   ================================================================ */
if (($_GET['aksi'] ?? '') === 'cari_gambar') {
    header('Content-Type: application/json');
    if (empty($_FILES['gambar']['name'])) {
        echo json_encode(['kesalahan' => 'Gambar tidak ditemukan.']);
        exit;
    }
    $info = @getimagesize($_FILES['gambar']['tmp_name']);
    if ($info === false) {
        echo json_encode(['kesalahan' => 'File yang diunggah bukan gambar yang valid.']);
        exit;
    }
    $eks = image_type_to_extension($info[2], false) ?: 'jpg';
    $tmp = sys_get_temp_dir() . '/' . uniqid('cnn_') . '.' . $eks;
    move_uploaded_file($_FILES['gambar']['tmp_name'], $tmp);

    $data = panggil_layanan_gambar($tmp);
    @unlink($tmp);

    if ($data === null) {
        echo json_encode(['kesalahan' => 'Layanan Python tidak dapat dihubungi. Pastikan aplikasi_layanan.py berjalan di ' . ALAMAT_LAYANAN_PYTHON . '.']);
        exit;
    }
    $hasil = perkaya_hasil_rekomendasi($koneksi, $data['hasil'] ?? []);

    /* Catat ke riwayat_pencarian */
    try {
        $koneksi->prepare(
            "INSERT INTO riwayat_pencarian (id_admin, jenis_pencarian, nama_file_dicari, waktu_proses_ms)
             VALUES (?, 'gambar', ?, ?)"
        )->execute([$_SESSION['id_admin'], basename($_FILES['gambar']['name'] ?? ''), $data['waktu_proses_ms'] ?? null]);
        $id_riwayat = (int) $koneksi->lastInsertId();
        $jumlah_per_kategori = [];
        foreach ($hasil as $item) {
          $id_kategori = (int) ($item['id_kategori'] ?? 0);
          if ($id_kategori > 0) $jumlah_per_kategori[$id_kategori] = ($jumlah_per_kategori[$id_kategori] ?? 0) + 1;
        }
        $simpan_kategori = $koneksi->prepare(
          "INSERT INTO riwayat_pencarian_kategori (id_riwayat, id_kategori, jumlah_hasil) VALUES (?, ?, ?)"
        );
        foreach ($jumlah_per_kategori as $id_kategori => $jumlah) {
          $simpan_kategori->execute([$id_riwayat, $id_kategori, $jumlah]);
        }
    } catch (Exception $e) { /* abaikan */ }

    echo json_encode(['hasil' => $hasil, 'waktu_ms' => $data['waktu_proses_ms'] ?? null]);
    exit;
}

/* ================================================================
   POST — buat pesanan
   ================================================================ */
$pesan = ''; $jenis_pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_pesanan'])) {
    $id_desain      = ($_POST['id_desain'] ?? '') !== '' ? (int)$_POST['id_desain'] : null;
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $nomor_telepon  = trim($_POST['nomor_telepon'] ?? '');
    $jumlah         = max(1, (int)($_POST['jumlah'] ?? 1));
    $teks_desain    = trim($_POST['teks_desain'] ?? '');
    $status         = in_array($_POST['status'] ?? '', ['menunggu_dp','diproses','selesai'])
                        ? $_POST['status'] : 'menunggu_dp';
    $jumlah_dp      = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah_dp'] ?? 0);
    $catatan        = trim($_POST['catatan'] ?? '');

    if ($nama_pelanggan === '') {
        $pesan = 'Nama pelanggan wajib diisi.';
        $jenis_pesan = 'gagal';
    } else {
        /* Simpan foto pelanggan jika ada */
        $nama_foto = null;
        if (!empty($_FILES['foto_pelanggan']['name'])) {
            $eks_foto = strtolower(pathinfo($_FILES['foto_pelanggan']['name'], PATHINFO_EXTENSION));
            if (in_array($eks_foto, ['jpg','jpeg','png','webp'])) {
                $nama_foto = uniqid('foto_') . '.' . $eks_foto;
                $dir_foto  = __DIR__ . '/../uploads/foto_pelanggan/';
                if (!is_dir($dir_foto)) {
                    mkdir($dir_foto, 0755, true);
                }
                move_uploaded_file($_FILES['foto_pelanggan']['tmp_name'], $dir_foto . $nama_foto);
            }
        }

        $koneksi->prepare(
            "INSERT INTO pesanan
             (id_desain, nama_pelanggan, nomor_telepon, jumlah, status, jumlah_dp,
              teks_desain, foto_pelanggan, catatan, dibuat_oleh)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $id_desain,
            $nama_pelanggan,
            $nomor_telepon !== '' ? $nomor_telepon : null,
            $jumlah,
            $status,
            $jumlah_dp,
            $teks_desain ?: null,
            $nama_foto,
            $catatan ?: null,
            $_SESSION['id_admin'],
        ]);

        header('Location: ' . BASE_URL . '/admin/kelola_pesanan.php?sukses=pesanan_dibuat');
        exit;
    }
}

$halaman_admin_aktif  = 'cari_pesan';
$judul_halaman_admin  = 'Cari & Pesan';
$subjudul_halaman_admin = 'Cari desain untuk pelanggan, lalu buat pesanan langsung';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cari & Pesan — Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
/* ===== TAB PENCARIAN ===== */
.tab-cari{display:flex;gap:0;border:1px solid var(--warna-batas);border-radius:10px;overflow:hidden;width:fit-content;margin-bottom:20px;}
.tab-cari button{padding:9px 22px;border:none;background:var(--warna-bg-kartu);color:var(--warna-teks-sekunder);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;}
.tab-cari button.aktif{background:var(--warna-utama);color:#fff;}
.tab-cari button:first-child{border-right:1px solid var(--warna-batas);}

/* ===== PANEL CARI ===== */
.panel-cari{background:var(--warna-bg-kartu);border:1px solid var(--warna-batas);border-radius:12px;padding:20px 22px;margin-bottom:24px;}
.area-gambar-cari{border:2px dashed var(--warna-batas);border-radius:10px;padding:28px 16px;text-align:center;cursor:pointer;transition:border-color .2s;position:relative;}
.area-gambar-cari:hover{border-color:var(--warna-utama);}
.area-gambar-cari input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.area-gambar-cari img{max-height:120px;border-radius:8px;margin-top:8px;display:none;}
#pratinjau-gambar{display:none;max-height:120px;border-radius:8px;margin-top:8px;}

/* ===== TOMBOL CARI ===== */
.tombol-cari{background:var(--warna-utama);color:#fff;border:none;border-radius:8px;padding:10px 26px;font-size:14px;font-weight:700;cursor:pointer;margin-top:12px;display:inline-flex;align-items:center;gap:8px;transition:opacity .2s;}
.tombol-cari:hover{opacity:.88;}
.tombol-cari:disabled{opacity:.5;cursor:not-allowed;}

/* ===== INDIKATOR LOADING ===== */
.spinner{display:none;width:18px;height:18px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* ===== HASIL ===== */
#area-hasil{margin-top:4px;}
.info-waktu{font-size:12px;color:var(--warna-teks-sekunder);margin-bottom:14px;}
.grid-hasil{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;}
.kartu-hasil{border:2px solid var(--warna-batas);border-radius:10px;overflow:hidden;cursor:pointer;transition:all .2s;background:var(--warna-bg-kartu);}
.kartu-hasil:hover{border-color:var(--warna-utama);transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.08);}
.kartu-hasil.terpilih{border-color:var(--warna-utama);box-shadow:0 0 0 3px rgba(107,30,43,.18);}
.kartu-hasil img{width:100%;height:130px;object-fit:cover;display:block;}
.info-hasil{padding:10px;}
.info-hasil .judul{font-size:13px;font-weight:600;margin-bottom:4px;color:var(--warna-teks-utama);}
.info-hasil .skor{font-size:12px;color:var(--warna-utama);font-weight:700;}
.info-hasil .meta{font-size:11px;color:var(--warna-teks-sekunder);margin-top:2px;}
.centang-pilih{display:none;position:absolute;top:7px;right:7px;background:var(--warna-utama);color:#fff;border-radius:50%;width:22px;height:22px;align-items:center;justify-content:center;font-size:13px;font-weight:900;}
.kartu-hasil{position:relative;}
.kartu-hasil.terpilih .centang-pilih{display:flex;}

/* ===== PANEL PESAN ===== */
#panel-pesan{display:none;background:var(--warna-bg-kartu);border:2px solid var(--warna-utama);border-radius:14px;padding:24px 26px;margin-top:24px;animation:munculPanel .25s ease;}
@keyframes munculPanel{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.header-panel-pesan{display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--warna-batas);}
.thumb-desain-terpilih{width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--warna-batas);flex-shrink:0;}
.grid-form-pesan{display:grid;grid-template-columns:1fr 1fr;gap:0 20px;}
.grid-form-pesan label{font-size:13px;font-weight:600;margin-top:14px;display:block;color:var(--warna-teks-utama);}
.grid-form-pesan input,.grid-form-pesan select,.grid-form-pesan textarea{width:100%;margin-top:5px;}
.span-full{grid-column:1/-1;}
.tombol-simpan-pesan{margin-top:20px;width:100%;padding:11px;font-size:14px;font-weight:700;}
.label-wajib{color:var(--admin-merah);}
.pesan-error-panel{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;color:#b91c1c;font-size:13px;margin-bottom:14px;}
.kosong-hasil{text-align:center;padding:36px 0;color:var(--warna-teks-sekunder);font-size:14px;}
.error-hasil{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px;color:#b91c1c;font-size:13px;}

/* ===== PREVIEW HASIL (ADMIN ONLY) ===== */
.modal-preview{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:9999;background:rgba(8,12,20,.92);padding:28px;backdrop-filter:blur(18px);}
.modal-preview.muncul{display:flex;animation:previewFade .18s ease forwards;}
@keyframes previewFade{from{opacity:0}to{opacity:1}}
.modal-preview__backdrop{position:absolute;inset:0;}
.modal-preview__dialog{position:relative;display:grid;grid-template-columns:minmax(460px,1.08fr) minmax(330px,.92fr);background:#f8fafc;border:1px solid rgba(255,255,255,.14);border-radius:22px;overflow:hidden;max-width:1080px;width:min(94vw,1080px);max-height:88vh;box-shadow:0 32px 100px rgba(0,0,0,.52);z-index:1;transform:translateY(10px) scale(.985);opacity:0;transition:transform .2s ease, opacity .2s ease;}
.modal-preview.muncul .modal-preview__dialog{opacity:1;transform:translateY(0) scale(1);}
.modal-preview__stage{position:relative;min-height:560px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:radial-gradient(ellipse at 50% 28%,#2b405f 0%,#162238 38%,#090d16 100%);perspective:1100px;}
.modal-preview__stage::before{content:"";position:absolute;inset:52% -15% -35%;background:linear-gradient(rgba(145,202,225,.12) 1px,transparent 1px),linear-gradient(90deg,rgba(145,202,225,.12) 1px,transparent 1px);background-size:42px 42px;transform:perspective(440px) rotateX(64deg);transform-origin:top;opacity:.48;}
.modal-preview__stage::after{content:"";position:absolute;width:310px;height:100px;top:18%;left:50%;transform:translateX(-50%);background:rgba(194,232,255,.16);filter:blur(45px);border-radius:50%;pointer-events:none;}
.modal-preview__card{position:relative;width:min(74%,430px);height:min(74vh,570px);transform-style:preserve-3d;transform:rotateX(0deg) rotateY(0deg) translateZ(0);transition:transform .18s ease;filter:drop-shadow(24px 30px 22px rgba(0,0,0,.48));z-index:1;}
.modal-preview__card::before{content:"";position:absolute;inset:-5px;border:1px solid rgba(202,240,255,.72);border-radius:13px;box-shadow:0 0 0 1px rgba(86,172,205,.25),0 0 24px rgba(105,215,255,.4),inset 0 0 0 7px rgba(255,255,255,.1);z-index:1;pointer-events:none;}
.modal-preview__img{width:100%;height:100%;object-fit:contain;background:#fff;display:block;border-radius:10px;transform:translateZ(18px);}
.modal-preview__body{padding:44px 42px 36px;display:flex;flex-direction:column;justify-content:center;background:linear-gradient(155deg,#f8fafc 0%,#e8eef7 100%);}
.modal-preview__label{font-size:10px;letter-spacing:1.4px;font-weight:800;text-transform:uppercase;color:#1686a8;margin-bottom:12px;}
.modal-preview__body h3{margin:0 0 12px;font-size:30px;line-height:1.16;color:#152238;}
.modal-preview__meta{font-size:14px;line-height:1.65;color:#526174;margin-bottom:18px;}
.modal-preview__skor{display:inline-block;padding:8px 12px;border-radius:8px;background:#dff7ff;color:#087b9d;font-weight:800;font-size:12px;margin-bottom:22px;}
.modal-preview__aksi{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;}
.modal-preview__tutup{border:1px solid #cbd5e1;background:#fff;color:#152238;padding:10px 16px;border-radius:8px;font-weight:700;cursor:pointer;}
.modal-preview__close{position:absolute;top:14px;right:14px;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.9);color:#152238;width:34px;height:34px;border-radius:50%;font-size:16px;cursor:pointer;z-index:2;}
@media (max-width: 680px){.modal-preview{padding:12px;align-items:flex-start;overflow:auto;} .modal-preview__dialog{grid-template-columns:1fr;max-height:95vh;overflow:auto;max-width:96vw;transform:translateY(10px) scale(.985);} .modal-preview__stage{min-height:300px;height:48vh;max-height:52vh;} .modal-preview__card{width:82%;height:42vh;max-height:330px;} .modal-preview__stage::after{top:12%;width:220px;height:70px;} .modal-preview__body{padding:28px 24px 24px;} .modal-preview__body h3{font-size:24px;}}
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

      <!-- ===== PANEL CARI ===== -->
      <div class="panel-cari">

        <!-- Tab -->
        <div class="tab-cari">
          <button id="tab-gambar" class="aktif" onclick="gantiTab('gambar')">
            🖼 Cari dengan Gambar
          </button>
          <button id="tab-teks" onclick="gantiTab('teks')">
            🔤 Cari dengan Kata Kunci
          </button>
        </div>

        <!-- Form gambar (CNN) -->
        <div id="form-gambar">
          <p style="font-size:13px;color:var(--warna-teks-sekunder);margin-bottom:12px;">
            Gunakan foto desain yang dibawa pelanggan sebagai referensi pencarian CNN.
          </p>
          <div class="area-gambar-cari" id="drop-gambar">
            <input type="file" id="input-gambar" accept="image/*" onchange="pratinjauGambar(this)">
            <div id="label-drop">
              <div style="font-size:28px;">📷</div>
              <div style="font-size:14px;font-weight:600;margin-top:6px;">Klik atau seret gambar ke sini</div>
              <div style="font-size:12px;color:var(--warna-teks-sekunder);margin-top:4px;">JPG, PNG, WEBP — maks 10 MB</div>
            </div>
            <img id="pratinjau-gambar" alt="Pratinjau">
          </div>
          <button class="tombol-cari" id="btn-cari-gambar" onclick="cariGambar()" disabled>
            <span class="spinner" id="spin-gambar"></span>
            🔍 Cari Desain (CNN)
          </button>
        </div>

        <!-- Form teks (TF-IDF) -->
        <div id="form-teks" style="display:none;">
          <p style="font-size:13px;color:var(--warna-teks-sekunder);margin-bottom:12px;">
            Masukkan kata kunci sesuai keinginan pelanggan — tema, warna, acara, dll.
          </p>
          <div style="display:flex;gap:10px;align-items:flex-end;">
            <div style="flex:1;">
              <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Kata Kunci</label>
              <input type="text" id="input-teks" placeholder="Contoh: pernikahan islami minimalis hijau"
                style="width:100%;" onkeydown="if(event.key==='Enter')cariTeks()">
            </div>
            <button class="tombol-cari" id="btn-cari-teks" onclick="cariTeks()">
              <span class="spinner" id="spin-teks"></span>
              🔍 Cari Desain (TF-IDF)
            </button>
          </div>
        </div>

      </div><!-- /panel-cari -->

      <!-- ===== AREA HASIL ===== -->
      <div id="area-hasil"></div>

      <!-- ===== PANEL PESAN ===== -->
      <div id="panel-pesan">
        <div class="header-panel-pesan">
          <img id="thumb-terpilih" src="" alt="Desain terpilih" class="thumb-desain-terpilih">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--warna-utama);letter-spacing:.5px;text-transform:uppercase;">Desain Dipilih</div>
            <div id="judul-terpilih" style="font-size:16px;font-weight:700;margin-top:3px;"></div>
            <div id="meta-terpilih" style="font-size:12px;color:var(--warna-teks-sekunder);margin-top:2px;"></div>
          </div>
          <button onclick="tutupPanelPesan()" style="margin-left:auto;border:none;background:none;font-size:20px;cursor:pointer;color:var(--warna-teks-sekunder);" title="Tutup">✕</button>
        </div>

        <div id="modal-preview" class="modal-preview" aria-hidden="true" role="dialog" aria-modal="true">
          <div class="modal-preview__backdrop" onclick="tutupPreview()"></div>
          <div class="modal-preview__dialog">
            <button type="button" class="modal-preview__close" onclick="tutupPreview()" aria-label="Tutup preview">✕</button>
            <div id="preview-3d-stage" class="modal-preview__stage" aria-label="Preview desain tiga dimensi">
              <div id="preview-3d-card" class="modal-preview__card">
                <img id="preview-gambar" class="modal-preview__img" src="" alt="Preview desain">
              </div>
            </div>
            <div class="modal-preview__body">
              <div class="modal-preview__label">Preview Hasil Pencarian</div>
              <h3 id="preview-judul"></h3>
              <div id="preview-meta" class="modal-preview__meta"></div>
              <div id="preview-skor" class="modal-preview__skor"></div>
              <div class="modal-preview__aksi">
                <button type="button" class="modal-preview__tutup" onclick="tutupPreview()">Tutup</button>
                <button type="button" class="tombol-utama" onclick="pilihDesainDariPreview()">Pilih untuk Pesanan</button>
              </div>
            </div>
          </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/cari_pesan.php" enctype="multipart/form-data">
          <input type="hidden" name="id_desain" id="hidden-id-desain">

          <div class="grid-form-pesan">

            <!-- Nama Pelanggan -->
            <div>
              <label>Nama Pelanggan <span class="label-wajib">*</span></label>
              <input type="text" name="nama_pelanggan" placeholder="Nama lengkap pelanggan" required>
            </div>

            <!-- Nomor Telepon -->
            <div>
              <label>Nomor Telepon</label>
              <input type="tel" name="nomor_telepon" placeholder="08xxxxxxxxxx">
            </div>

            <!-- Jumlah -->
            <div>
              <label>Jumlah Cetak (lembar) <span class="label-wajib">*</span></label>
              <input type="number" name="jumlah" min="1" value="100">
            </div>

            <!-- DP -->
            <div>
              <label>Uang Muka / DP (Rp)</label>
              <input type="number" name="jumlah_dp" min="0" step="1000" value="0" placeholder="0">
            </div>

            <!-- Status -->
            <div>
              <label>Status Awal</label>
              <select name="status">
                <option value="menunggu_dp">Menunggu DP</option>
                <option value="diproses">Diproses</option>
                <option value="selesai">Selesai</option>
              </select>
            </div>

            <!-- Foto pelanggan -->
            <div>
              <label>Foto Pelanggan (untuk desain)</label>
              <input type="file" name="foto_pelanggan" accept="image/*"
                style="padding:6px 0;border:none;background:none;width:100%;">
              <div style="font-size:11px;color:var(--warna-teks-sekunder);margin-top:3px;">Foto yang ingin dimasukkan ke undangan</div>
            </div>

            <!-- Teks desain (span full) -->
            <div class="span-full">
              <label>Teks / Kata-Kata Desain</label>
              <textarea name="teks_desain" rows="3"
                placeholder="Contoh:&#10;Nama: Budi Santoso & Sari Dewi&#10;Tanggal: Sabtu, 10 Januari 2027&#10;Tempat: Gedung Serbaguna Harmoni, Medan"></textarea>
              <div style="font-size:11px;color:var(--warna-teks-sekunder);margin-top:3px;">Nama, tanggal, tempat, dan detail lain yang perlu ditulis di undangan</div>
            </div>

            <!-- Catatan admin (span full) -->
            <div class="span-full">
              <label>Catatan Tambahan (admin)</label>
              <textarea name="catatan" rows="2" placeholder="Catatan internal: misal keterangan DP, permintaan khusus, dll"></textarea>
            </div>

          </div>

          <button type="submit" name="buat_pesanan" value="1" class="tombol-utama tombol-simpan-pesan">
            ✅ Simpan Pesanan
          </button>
        </form>
      </div><!-- /panel-pesan -->

    </div><!-- /isi-admin -->
  </div>
</div>

<script>
/* ================================================================
   STATE
   ================================================================ */
let tabAktif    = 'gambar';   // 'gambar' | 'teks'
let idTerpilih  = null;       // id_desain yang dipilih admin

/* ================================================================
   GANTI TAB
   ================================================================ */
function gantiTab(tab) {
  tabAktif = tab;
  document.getElementById('form-gambar').style.display = tab === 'gambar' ? '' : 'none';
  document.getElementById('form-teks').style.display   = tab === 'teks'   ? '' : 'none';
  document.getElementById('tab-gambar').className = tab === 'gambar' ? 'aktif' : '';
  document.getElementById('tab-teks').className   = tab === 'teks'   ? 'aktif' : '';
  // Bersihkan hasil ketika ganti tab
  bersihkanHasil();
}

/* ================================================================
   PRATINJAU GAMBAR
   ================================================================ */
function pratinjauGambar(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('pratinjau-gambar');
    document.getElementById('label-drop').style.display = 'none';
    img.src = e.target.result;
    img.style.display = 'block';
    document.getElementById('btn-cari-gambar').disabled = false;
  };
  reader.readAsDataURL(file);
}

/* ================================================================
   CARI GAMBAR (CNN)
   ================================================================ */
async function cariGambar() {
  const input = document.getElementById('input-gambar');
  if (!input.files[0]) return;

  const btn  = document.getElementById('btn-cari-gambar');
  const spin = document.getElementById('spin-gambar');
  btn.disabled = true;
  spin.style.display = 'block';
  bersihkanHasil();
  tutupPanelPesan();

  const fd = new FormData();
  fd.append('gambar', input.files[0]);

  try {
    const resp = await fetch('<?= BASE_URL ?>/admin/cari_pesan.php?aksi=cari_gambar', {
      method: 'POST',
      body: fd,
    });
    const data = await resp.json();
    tampilkanHasil(data, 'CNN MobileNetV2');
  } catch (e) {
    tampilkanError('Terjadi kesalahan jaringan saat menghubungi server.');
  } finally {
    btn.disabled = false;
    spin.style.display = 'none';
  }
}

/* ================================================================
   CARI TEKS (TF-IDF)
   ================================================================ */
async function cariTeks() {
  const kata = document.getElementById('input-teks').value.trim();
  if (!kata) {
    document.getElementById('input-teks').focus();
    return;
  }

  const btn  = document.getElementById('btn-cari-teks');
  const spin = document.getElementById('spin-teks');
  btn.disabled = true;
  spin.style.display = 'block';
  bersihkanHasil();
  tutupPanelPesan();

  try {
    const resp = await fetch('<?= BASE_URL ?>/admin/cari_pesan.php?aksi=cari_teks', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({kata_kunci: kata}),
    });
    const data = await resp.json();
    tampilkanHasil(data, 'TF-IDF');
  } catch (e) {
    tampilkanError('Terjadi kesalahan jaringan saat menghubungi server.');
  } finally {
    btn.disabled = false;
    spin.style.display = 'none';
  }
}

/* ================================================================
   TAMPILKAN HASIL
   ================================================================ */
function tampilkanHasil(data, metode) {
  const area = document.getElementById('area-hasil');

  if (data.kesalahan) {
    area.innerHTML = `<div class="error-hasil">⚠️ ${escHtml(data.kesalahan)}</div>`;
    return;
  }

  const hasil   = data.hasil || [];
  const waktuMs = data.waktu_ms ? parseFloat(data.waktu_ms).toFixed(1) : null;

  if (hasil.length === 0) {
    area.innerHTML = `<div class="kosong-hasil">🔍 Tidak ada desain yang cocok dengan pencarian ini.</div>`;
    return;
  }

  const infoWaktu = waktuMs
    ? `<div class="info-waktu">${metode} — ${hasil.length} desain ditemukan &middot; waktu proses: <b>${waktuMs} ms</b></div>`
    : `<div class="info-waktu">${metode} — ${hasil.length} desain ditemukan</div>`;

  const kartuHtml = hasil.map(h => {
    const skor     = (parseFloat(h.skor_kemiripan) * 100).toFixed(1);
    const kateg    = h.nama_kategori ? `<span>${escHtml(h.nama_kategori)}</span>` : '';
    const tema     = h.tema ? `<span>${escHtml(h.tema)}</span>` : '';
    const metaStr  = [kateg, tema].filter(Boolean).join(' &middot; ');
    const imgSrc   = `<?= BASE_URL ?>/uploads/desain/${escHtml(h.nama_file_gambar)}`;

    return `
      <div class="kartu-hasil" data-id="${h.id_desain}"
           data-judul="${escHtml(h.judul_desain)}"
           data-img="${imgSrc}"
           data-meta="${escHtml((h.nama_kategori||'') + (h.tema ? ' · '+h.tema : ''))}"
           data-skor="${skor}%"
           onclick="pilihDesain(this); bukaPreview(this);">
        <div class="centang-pilih">✓</div>
        <img src="${imgSrc}" alt="${escHtml(h.judul_desain)}" loading="lazy" decoding="async">
        <div class="info-hasil">
          <div class="judul">${escHtml(h.judul_desain)}</div>
          <div class="skor">Kemiripan: ${skor}%</div>
          <div class="meta">${metaStr}</div>
        </div>
      </div>`;
  }).join('');

  area.innerHTML = infoWaktu + `<div class="grid-hasil">${kartuHtml}</div>`;
}

/* ================================================================
   PILIH DESAIN → buka panel pesan
   ================================================================ */
function pilihDesain(kartu) {
  /* Tandai kartu yang dipilih */
  document.querySelectorAll('.kartu-hasil').forEach(k => k.classList.remove('terpilih'));
  kartu.classList.add('terpilih');
  idTerpilih = kartu.dataset.id;

  /* Isi header panel pesan */
  document.getElementById('hidden-id-desain').value   = idTerpilih;
  document.getElementById('thumb-terpilih').src        = kartu.dataset.img;
  document.getElementById('judul-terpilih').textContent = kartu.dataset.judul;
  document.getElementById('meta-terpilih').textContent  = kartu.dataset.meta;

  /* Tampilkan panel pesan */
  const panel = document.getElementById('panel-pesan');
  panel.style.display = 'block';

  /* Gulir ke panel pesan */
  setTimeout(() => panel.scrollIntoView({behavior: 'smooth', block: 'start'}), 50);
}

function bukaPreview(kartu) {
  if (!kartu) return;

  const modal = document.getElementById('modal-preview');
  const dialog = document.querySelector('.modal-preview__dialog');
  if (!modal || !dialog) return;

  document.getElementById('preview-gambar').src = kartu.dataset.img || '';
  document.getElementById('preview-judul').textContent = kartu.dataset.judul || '';
  document.getElementById('preview-meta').textContent = kartu.dataset.meta || '';
  document.getElementById('preview-skor').textContent = `Kemiripan: ${kartu.dataset.skor || '0%'}`;

  document.body.classList.add('modal-preview-open');
  dialog.style.transform = 'translateY(0) scale(1)';
  modal.classList.add('muncul');
  modal.setAttribute('aria-hidden', 'false');

  const panggung = document.getElementById('preview-3d-stage');
  const kartu3d = document.getElementById('preview-3d-card');
  if (panggung && kartu3d) {
    panggung.onpointermove = function(event) {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const kotak = panggung.getBoundingClientRect();
      const x = (event.clientX - kotak.left) / kotak.width - 0.5;
      const y = (event.clientY - kotak.top) / kotak.height - 0.5;
      kartu3d.style.transform = `rotateX(${(-y * 12).toFixed(2)}deg) rotateY(${(x * 15).toFixed(2)}deg) translateZ(18px)`;
    };
    panggung.onpointerleave = function() {
      kartu3d.style.transform = 'rotateX(0deg) rotateY(0deg) translateZ(0)';
    };
  }
}

function pilihDesainDariPreview() {
  const kartu = document.querySelector('.kartu-hasil.terpilih') || document.querySelector('.kartu-hasil');
  if (!kartu) return;
  pilihDesain(kartu);
  tutupPreview();
}

/* ================================================================
   TUTUP PANEL PESAN
   ================================================================ */
function tutupPanelPesan() {
  document.getElementById('panel-pesan').style.display = 'none';
  document.querySelectorAll('.kartu-hasil').forEach(k => k.classList.remove('terpilih'));
  idTerpilih = null;
}

function tutupPreview() {
  const modal = document.getElementById('modal-preview');
  const dialog = document.querySelector('.modal-preview__dialog');
  if (!modal) return;
  document.body.classList.remove('modal-preview-open');
  modal.classList.remove('muncul');
  modal.setAttribute('aria-hidden', 'true');
  if (dialog) {
    dialog.style.transform = 'translateY(12px) scale(.98)';
  }
  const panggung = document.getElementById('preview-3d-stage');
  const kartu3d = document.getElementById('preview-3d-card');
  if (panggung) { panggung.onpointermove = null; panggung.onpointerleave = null; }
  if (kartu3d) kartu3d.style.transform = 'rotateX(0deg) rotateY(0deg) translateZ(0)';
}

/* ================================================================
   HELPERS
   ================================================================ */
function bersihkanHasil() {
  document.getElementById('area-hasil').innerHTML = '';
  tutupPanelPesan();
}

function tampilkanError(msg) {
  document.getElementById('area-hasil').innerHTML =
    `<div class="error-hasil">⚠️ ${escHtml(msg)}</div>`;
}

function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* Jika ada error validasi PHP (form dikembalikan), buka panel pesan kembali */
<?php if ($pesan && $jenis_pesan === 'gagal'): ?>
(function() {
  const panel = document.getElementById('panel-pesan');
  if (panel) {
    panel.style.display = 'block';
    panel.scrollIntoView({behavior: 'smooth', block: 'start'});
  }
})();
<?php endif; ?>
</script>
</body>
</html>

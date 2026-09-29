<?php
/**
 * CETAK_KWITANSI.PHP
 * Bukti pembayaran dalam format struk printer thermal 80 mm.
 * Halaman ini hanya dapat dibuka oleh admin dan otomatis membuka dialog print.
 */
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_rupiah(float $nominal): string
{
    return 'Rp' . number_format($nominal, 0, ',', '.');
}

function format_tanggal(?string $tanggal): string
{
    $timestamp = $tanggal ? strtotime($tanggal) : false;
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
}

function nomor_kwitansi(int $id_pesanan): string
{
    return 'KW-' . str_pad((string) $id_pesanan, 6, '0', STR_PAD_LEFT);
}

function tampilkan_pesanan_tidak_ditemukan(): void
{
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Kwitansi Tidak Ditemukan — Rotama Jaya</title>
      <style>
        body{margin:0;padding:24px;font-family:Arial,sans-serif;color:#2A1315;background:#FDF8F2;}
        .pesan{max-width:420px;margin:10vh auto;padding:24px;background:#fff;border:1px solid #EAD9C4;border-radius:12px;}
        a{color:#6B1E2B;font-weight:700;}
      </style>
    </head>
    <body>
      <main class="pesan">
        <h1 style="font-size:20px;margin-top:0;">Kwitansi tidak ditemukan</h1>
        <p>Nomor pesanan yang diminta tidak tersedia.</p>
        <a href="<?= esc(BASE_URL) ?>/admin/kelola_pesanan.php">&larr; Kembali ke pesanan</a>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$id_pesanan = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id_pesanan === false || $id_pesanan === null || $id_pesanan < 1) {
    tampilkan_pesanan_tidak_ditemukan();
}

$pernyataan = $koneksi->prepare(
    "SELECT p.id_pesanan, p.nama_pelanggan, p.nomor_telepon, p.jumlah,
            p.status, p.jumlah_dp, p.teks_desain, p.catatan, p.dibuat_pada,
            d.judul_desain
     FROM pesanan p
     LEFT JOIN desain_undangan d ON d.id_desain = p.id_desain
     WHERE p.id_pesanan = ?"
);
$pernyataan->execute([$id_pesanan]);
$pesanan = $pernyataan->fetch();

if (!$pesanan) {
    tampilkan_pesanan_tidak_ditemukan();
}

$peta_status = [
    'menunggu_dp' => 'Menunggu DP',
    'diproses'    => 'Diproses',
    'selesai'     => 'Selesai',
];
$status = $pesanan['status'] ?? '';
$label_status = $peta_status[$status] ?? 'Tidak tersedia';
$kelas_status = array_key_exists($status, $peta_status) ? $status : 'tidak-tersedia';
$nama_admin = $_SESSION['nama_admin'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc(nomor_kwitansi((int) $pesanan['id_pesanan'])) ?> — Rotama Jaya</title>
<style>
:root {
    --struk-lebar: 80mm;
    --struk-area-cetak: 72mm;
    --struk-padding: 4mm;
    --struk-font: Arial, sans-serif;
    --struk-font-isi: 11px;
    --struk-font-usaha: 17px;
    --struk-font-total: 16px;
    --struk-kertas: #ffffff;
    --struk-tinta: #111827;
    --struk-teks-redup: #4b5563;
    --struk-aksen: #6B1E2B;
    --struk-garis: #1f2937;
    --struk-garis-lebar: 1px;
    --struk-jarak-baris: 2.5mm;
    --struk-radius-pill: 999px;
}

@page {
    size: 80mm auto;
    margin: 0;
}

* {
    box-sizing: border-box;
}

html {
    background: #e5e7eb;
}

body {
    width: var(--struk-lebar);
    margin: 0 auto;
    padding: var(--struk-padding);
    background: var(--struk-kertas);
    color: var(--struk-tinta);
    font-family: var(--struk-font);
    font-size: var(--struk-font-isi);
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.toolbar {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    margin: 0 auto 12px;
}

.tombol-cetak,
.tombol-kembali {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 6px 9px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #fff;
    color: var(--struk-aksen);
    font: 700 11px/1.2 var(--struk-font);
    text-decoration: none;
    cursor: pointer;
}

.tombol-cetak {
    border-color: var(--struk-aksen);
    background: var(--struk-aksen);
    color: #fff;
}

.tombol-cetak:hover,
.tombol-kembali:hover {
    opacity: .86;
}

.struk {
    width: 100%;
    max-width: var(--struk-area-cetak);
    margin: 0 auto;
}

.kepala-struk {
    text-align: center;
}

.logo-usaha {
    display: block;
    width: auto;
    height: auto;
    max-width: 32mm;
    max-height: 18mm;
    margin: 0 auto 2mm;
    object-fit: contain;
}

.nama-usaha {
    color: var(--struk-aksen);
    font-size: var(--struk-font-usaha);
    font-weight: 800;
    line-height: 1.15;
}

.keterangan-usaha {
    margin: 1mm 0 0;
    color: var(--struk-teks-redup);
    font-size: 10px;
}

.garis-pemisah {
    width: 100%;
    margin: 3mm 0;
    border-top: var(--struk-garis-lebar) dashed var(--struk-garis);
}

.judul-struk {
    margin: 0;
    color: var(--struk-aksen);
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .25px;
    text-align: center;
}

.meta-kwitansi {
    margin-top: 2mm;
}

.kelompok {
    margin-top: var(--struk-jarak-baris);
}

.label-kelompok {
    margin-bottom: 1mm;
    color: var(--struk-teks-redup);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
}

.detail-list {
    margin: 0;
}

.detail-row {
    display: grid;
    grid-template-columns: minmax(25mm, 1fr) minmax(0, 1.5fr);
    gap: 3mm;
    align-items: start;
    padding: 1.25mm 0;
    border-bottom: 1px dotted #d1d5db;
}

.detail-row:last-child {
    border-bottom: 0;
}

.detail-row dt,
.detail-row dd {
    min-width: 0;
    margin: 0;
}

.detail-row dt {
    color: var(--struk-teks-redup);
}

.detail-row dd {
    color: var(--struk-tinta);
    font-weight: 700;
    text-align: right;
}

.nilai-normal {
    font-weight: 400 !important;
}

.total-dp {
    margin-top: 3mm;
    padding: 2.5mm 0;
    border-top: 1px solid var(--struk-garis);
    border-bottom: 1px solid var(--struk-garis);
    text-align: center;
}

.label-total-dp {
    color: var(--struk-teks-redup);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .8px;
    text-transform: uppercase;
}

.nilai-total-dp {
    display: block;
    margin-top: 1mm;
    color: var(--struk-aksen);
    font-size: var(--struk-font-total);
    font-weight: 900;
    line-height: 1.15;
}

.status-pesanan {
    display: inline-block;
    padding: 1mm 2mm;
    border-radius: var(--struk-radius-pill);
    font-size: 10px;
    line-height: 1.1;
    white-space: normal;
}

.status-pesanan.menunggu_dp {
    background: #fef3c7;
    color: #92400e;
}

.status-pesanan.diproses {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-pesanan.selesai {
    background: #dcfce7;
    color: #166534;
}

.status-pesanan.tidak-tersedia {
    background: #f3f4f6;
    color: var(--struk-teks-redup);
}

.kotak-catatan {
    margin-top: 1mm;
    padding: 2mm;
    border: 1px solid #d1d5db;
    white-space: pre-wrap;
}

.pernyataan {
    margin: 0;
    color: var(--struk-teks-redup);
    font-size: 10px;
    text-align: center;
}

.area-admin {
    margin-top: 4mm;
    text-align: center;
}

.label-admin {
    color: var(--struk-teks-redup);
    font-size: 10px;
}

.garis-tanda-tangan {
    width: 34mm;
    margin: 7mm auto 1mm;
    border-top: 1px solid var(--struk-garis);
}

.nama-admin {
    font-weight: 700;
}

.footer-struk {
    margin: 4mm 0 0;
    color: var(--struk-teks-redup);
    font-size: 10px;
    text-align: center;
}

@media screen {
    body {
        min-height: 100vh;
    }

    .struk {
        padding: 5mm 0 2mm;
    }
}

@media print {
    .tombol-cetak,
    .tombol-kembali,
    .toolbar {
        display: none !important;
    }

    html,
    body {
        width: var(--struk-lebar);
        min-width: 0;
        margin: 0 auto;
        background: var(--struk-kertas);
    }

    body {
        padding: var(--struk-padding);
    }

    .struk {
        max-width: var(--struk-area-cetak);
        padding: 0;
    }
}
</style>
</head>
<body>
  <div class="toolbar" role="toolbar" aria-label="Aksi kwitansi">
    <a class="tombol-kembali" href="<?= esc(BASE_URL) ?>/admin/kelola_pesanan.php">&larr; Kembali</a>
    <button class="tombol-cetak" type="button" onclick="window.print()">Cetak Lagi</button>
  </div>

  <main class="struk" aria-label="Bukti pembayaran">
    <header class="kepala-struk">
      <img class="logo-usaha" src="<?= esc(BASE_URL) ?>/assets/img/logo_rotama_jaya.png" alt="Logo Rotama Jaya">
      <div class="nama-usaha">Percetakan Rotama Jaya</div>
      <p class="keterangan-usaha">Percetakan Undangan &amp; Desain</p>
      <p class="keterangan-usaha">Telp. 081396191081</p>
    </header>

    <div class="garis-pemisah" aria-hidden="true"></div>

    <h1 class="judul-struk">BUKTI PEMBAYARAN</h1>

    <dl class="detail-list meta-kwitansi">
      <div class="detail-row">
        <dt>No. Kwitansi</dt>
        <dd><?= esc(nomor_kwitansi((int) $pesanan['id_pesanan'])) ?></dd>
      </div>
      <div class="detail-row">
        <dt>Tanggal</dt>
        <dd><?= esc(format_tanggal($pesanan['dibuat_pada'])) ?></dd>
      </div>
    </dl>

    <section class="kelompok" aria-labelledby="label-pelanggan">
      <div class="label-kelompok" id="label-pelanggan">Data Pelanggan</div>
      <dl class="detail-list">
        <div class="detail-row">
          <dt>Nama</dt>
          <dd><?= esc($pesanan['nama_pelanggan']) ?></dd>
        </div>
        <div class="detail-row">
          <dt>Telepon</dt>
          <dd class="<?= empty($pesanan['nomor_telepon']) ? 'nilai-normal' : '' ?>">
            <?= esc($pesanan['nomor_telepon'] ?: 'Tidak tersedia') ?>
          </dd>
        </div>
      </dl>
    </section>

    <section class="kelompok" aria-labelledby="label-pesanan">
      <div class="label-kelompok" id="label-pesanan">Data Pesanan</div>
      <dl class="detail-list">
        <div class="detail-row">
          <dt>ID Pesanan</dt>
          <dd>#<?= esc(str_pad((string) $pesanan['id_pesanan'], 4, '0', STR_PAD_LEFT)) ?></dd>
        </div>
        <div class="detail-row">
          <dt>Desain</dt>
          <dd class="<?= empty($pesanan['judul_desain']) ? 'nilai-normal' : '' ?>">
            <?= esc($pesanan['judul_desain'] ?: 'Tidak tersedia') ?>
          </dd>
        </div>
        <div class="detail-row">
          <dt>Jumlah Cetak</dt>
          <dd><?= esc(number_format((int) $pesanan['jumlah'], 0, ',', '.')) ?> lembar</dd>
        </div>
        <div class="detail-row">
          <dt>Status</dt>
          <dd><span class="status-pesanan <?= esc($kelas_status) ?>"><?= esc($label_status) ?></span></dd>
        </div>
      </dl>
    </section>

    <div class="total-dp" aria-label="Jumlah DP yang dibayarkan">
      <span class="label-total-dp">Jumlah DP Dibayarkan</span>
      <strong class="nilai-total-dp"><?= esc(format_rupiah((float) $pesanan['jumlah_dp'])) ?></strong>
    </div>

    <?php if (!empty($pesanan['teks_desain'])): ?>
    <section class="kelompok" aria-labelledby="label-teks-desain">
      <div class="label-kelompok" id="label-teks-desain">Teks Desain</div>
      <div class="kotak-catatan"><?= esc($pesanan['teks_desain']) ?></div>
    </section>
    <?php endif; ?>

    <?php if (!empty($pesanan['catatan'])): ?>
    <section class="kelompok" aria-labelledby="label-catatan">
      <div class="label-kelompok" id="label-catatan">Catatan</div>
      <div class="kotak-catatan"><?= esc($pesanan['catatan']) ?></div>
    </section>
    <?php endif; ?>

    <div class="garis-pemisah" aria-hidden="true"></div>

    <p class="pernyataan">Pembayaran DP telah dicatat oleh sistem.</p>

    <div class="area-admin">
      <div class="label-admin">Admin</div>
      <div class="garis-tanda-tangan" aria-hidden="true"></div>
      <div class="nama-admin"><?= esc($nama_admin) ?></div>
    </div>

    <p class="footer-struk">Terima kasih telah menggunakan layanan Rotama Jaya</p>
  </main>

<script>
window.addEventListener('load', function () {
    window.setTimeout(function () {
        window.print();
    }, 150);
});
</script>
</body>
</html>

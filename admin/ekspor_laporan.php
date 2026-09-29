<?php
/**
 * EKSPOR_LAPORAN.PHP
 * Mengekspor data dari halaman Laporan Pengujian CNN & TF-IDF
 * (admin/laporan_pengujian_cnn.php, admin/laporan_pengujian_tfidf.php)
 * ke satu berkas Word (.doc) atau PDF gabungan, untuk lampiran skripsi.
 *
 * Cara pakai: /admin/ekspor_laporan.php?format=word  atau  ?format=pdf
 *
 * - Ekspor Word dibuat dalam format HTML berpembungkus MIME Word (application/msword)
 *   sehingga bisa langsung dibuka rapi oleh Microsoft Word/WPS/LibreOffice tanpa
 *   perlu pustaka tambahan (composer/PhpWord) di server.
 * - Ekspor PDF dibuat memakai pembuat PDF minimal tanpa dependensi (lihat
 *   includes/pdf_sederhana.php) supaya tetap berjalan di hosting mana pun.
 */
require_once __DIR__ . '/../koneksi_database.php';
require_once __DIR__ . '/../includes/pdf_sederhana.php';
periksa_sesi_admin();

$format = $_GET['format'] ?? 'pdf';
$format = in_array($format, ['word', 'pdf'], true) ? $format : 'pdf';

$data_cnn = $koneksi->query(
    "SELECT nomor_skenario, nama_gambar_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10
     FROM pengujian_cnn ORDER BY nomor_skenario"
)->fetchAll();
$data_tfidf = $koneksi->query(
    "SELECT nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10
     FROM pengujian_tfidf ORDER BY nomor_skenario"
)->fetchAll();

function rerata_ekspor($baris, $kolom) {
    if (!$baris) return 0;
    return array_sum(array_column($baris, $kolom)) / count($baris);
}
$kolom_metrik = ['presisi_5', 'presisi_10', 'ingatan_5', 'ingatan_10', 'skor_f1_5', 'skor_f1_10'];
$rerata_cnn = [];
$rerata_tfidf = [];
foreach ($kolom_metrik as $k) {
    $rerata_cnn[$k] = rerata_ekspor($data_cnn, $k);
    $rerata_tfidf[$k] = rerata_ekspor($data_tfidf, $k);
}

$nama_hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$sekarang = new DateTime();
$tanggal_cetak = $nama_hari[(int)$sekarang->format('w')] . ', ' . (int)$sekarang->format('j') . ' ' .
    $nama_bulan[(int)$sekarang->format('n')] . ' ' . $sekarang->format('Y') . ' ' . $sekarang->format('H:i');

$nama_berkas_dasar = 'laporan_pengujian_rotama_jaya_' . $sekarang->format('Ymd_His');
$mode_unduh = isset($_GET['unduh']) && $_GET['unduh'] === '1';

/* ============================================================
   PREVIEW HTML — ditampilkan lebih dahulu sebelum file diunduh
   ============================================================ */
if (!$mode_unduh) {
    $url_unduh_pdf = BASE_URL . '/admin/ekspor_laporan.php?format=pdf&unduh=1';
    $url_unduh_word = BASE_URL . '/admin/ekspor_laporan.php?format=word&unduh=1';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Laporan - Rotama Jaya</title>
    <style>
      :root { --marun:#6B1E2B; --emas:#C9962F; --teks:#2A1315; --batas:#dccbb8; }
      * { box-sizing:border-box; }
      body { margin:0; background:#eef1f5; color:var(--teks); font-family:Arial,sans-serif; }
      .toolbar { position:sticky; top:0; z-index:5; display:flex; gap:10px; justify-content:flex-end; padding:14px max(20px,calc((100% - 1040px)/2)); background:#fff; border-bottom:1px solid #d9dee7; }
      .toolbar a { padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13px; }
      .btn-pdf { color:#fff; background:var(--marun); }
      .btn-word { color:var(--teks); background:#fff; border:1px solid var(--batas); }
      .lembar { width:min(100% - 28px,1040px); margin:24px auto 50px; padding:42px 48px 52px; background:#fff; box-shadow:0 8px 28px rgba(27,36,56,.12); }
      .kop { display:flex; align-items:center; gap:16px; padding-bottom:14px; border-bottom:3px solid var(--marun); position:relative; }
      .kop:after { content:""; position:absolute; left:0; right:0; bottom:-7px; border-bottom:1px solid var(--emas); }
      .kop img { width:62px; height:62px; object-fit:contain; }
      .kop h1 { margin:0; color:var(--marun); font-size:22px; letter-spacing:.5px; }
      .kop p { margin:4px 0 0; color:#6b625c; font-size:11px; line-height:1.5; }
      .judul { text-align:center; margin:30px 0 24px; }
      .judul h2 { margin:0 0 7px; font-size:20px; color:var(--marun); }
      .judul p { margin:0; color:#6b625c; font-size:12px; }
      h3 { margin:28px 0 9px; padding-bottom:6px; color:var(--marun); border-bottom:1px solid var(--emas); font-size:15px; }
      table { width:100%; border-collapse:collapse; margin:8px 0 18px; font-size:10px; }
      th,td { border:1px solid var(--batas); padding:7px 6px; text-align:center; }
      th { background:#f7eee2; color:var(--teks); }
      td:nth-child(2) { text-align:left; }
      .catatan { margin-top:28px; padding-top:12px; border-top:1px solid var(--batas); color:#756b65; font-size:10px; line-height:1.5; }
      @media(max-width:700px){ .toolbar{justify-content:stretch; flex-wrap:wrap;} .toolbar a{flex:1;text-align:center;} .lembar{padding:26px 16px 34px;overflow-x:auto;} .kop h1{font-size:17px;} table{min-width:760px;} }
    </style>
    </head>
    <body>
      <div class="toolbar">
        <a class="btn-word" href="<?= htmlspecialchars($url_unduh_word, ENT_QUOTES, 'UTF-8') ?>">Unduh Word (.doc)</a>
        <a class="btn-pdf" href="<?= htmlspecialchars($url_unduh_pdf, ENT_QUOTES, 'UTF-8') ?>">Unduh PDF</a>
      </div>
      <main class="lembar">
        <header class="kop">
          <img src="<?= htmlspecialchars(BASE_URL . '/assets/img/logo_rotama_jaya.png', ENT_QUOTES, 'UTF-8') ?>" alt="Logo Rotama Jaya">
          <div>
            <h1>ROTAMA JAYA</h1>
            <p>Percetakan dan Sistem Rekomendasi Desain Undangan<br>Dokumen laporan pengujian metode rekomendasi</p>
          </div>
        </header>
        <div class="judul">
          <h2>LAPORAN PENGUJIAN SISTEM REKOMENDASI</h2>
          <p>CNN MobileNetV2 dan TF-IDF + Kesamaan Kosinus<br>Dicetak pada: <?= htmlspecialchars($tanggal_cetak) ?></p>
        </div>
        <h3>Ringkasan Rata-Rata Metrik</h3>
        <table>
          <tr><th>Metode</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th></tr>
          <tr><td>CNN MobileNetV2</td><td><?= number_format($rerata_cnn['presisi_5'],4) ?></td><td><?= number_format($rerata_cnn['presisi_10'],4) ?></td><td><?= number_format($rerata_cnn['ingatan_5'],4) ?></td><td><?= number_format($rerata_cnn['ingatan_10'],4) ?></td><td><?= number_format($rerata_cnn['skor_f1_5'],4) ?></td><td><?= number_format($rerata_cnn['skor_f1_10'],4) ?></td></tr>
          <tr><td>TF-IDF + Kesamaan Kosinus</td><td><?= number_format($rerata_tfidf['presisi_5'],4) ?></td><td><?= number_format($rerata_tfidf['presisi_10'],4) ?></td><td><?= number_format($rerata_tfidf['ingatan_5'],4) ?></td><td><?= number_format($rerata_tfidf['ingatan_10'],4) ?></td><td><?= number_format($rerata_tfidf['skor_f1_5'],4) ?></td><td><?= number_format($rerata_tfidf['skor_f1_10'],4) ?></td></tr>
        </table>
        <h3>1. Pengujian CNN MobileNetV2 (<?= count($data_cnn) ?> skenario)</h3>
        <table>
          <tr><th>Skenario</th><th>Gambar Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>F1@5</th><th>F1@10</th></tr>
          <?php foreach ($data_cnn as $b): ?><tr><td><?= (int)$b['nomor_skenario'] ?></td><td><?= htmlspecialchars($b['nama_gambar_uji']) ?></td><td><?= number_format((float)$b['presisi_5'],4) ?></td><td><?= number_format((float)$b['presisi_10'],4) ?></td><td><?= number_format((float)$b['ingatan_5'],4) ?></td><td><?= number_format((float)$b['ingatan_10'],4) ?></td><td><?= number_format((float)$b['skor_f1_5'],4) ?></td><td><?= number_format((float)$b['skor_f1_10'],4) ?></td></tr><?php endforeach; ?>
          <?php if (!$data_cnn): ?><tr><td colspan="8">Belum ada data pengujian CNN.</td></tr><?php endif; ?>
        </table>
        <h3>2. Pengujian TF-IDF + Kesamaan Kosinus (<?= count($data_tfidf) ?> skenario)</h3>
        <table>
          <tr><th>Skenario</th><th>Kata Kunci Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>F1@5</th><th>F1@10</th></tr>
          <?php foreach ($data_tfidf as $b): ?><tr><td><?= (int)$b['nomor_skenario'] ?></td><td><?= htmlspecialchars($b['kata_kunci_uji']) ?></td><td><?= number_format((float)$b['presisi_5'],4) ?></td><td><?= number_format((float)$b['presisi_10'],4) ?></td><td><?= number_format((float)$b['ingatan_5'],4) ?></td><td><?= number_format((float)$b['ingatan_10'],4) ?></td><td><?= number_format((float)$b['skor_f1_5'],4) ?></td><td><?= number_format((float)$b['skor_f1_10'],4) ?></td></tr><?php endforeach; ?>
          <?php if (!$data_tfidf): ?><tr><td colspan="8">Belum ada data pengujian TF-IDF.</td></tr><?php endif; ?>
        </table>
        <p class="catatan">Dokumen ini dibuat otomatis oleh Sistem Rekomendasi Desain Undangan Rotama Jaya untuk kebutuhan admin dan laporan penelitian.</p>
      </main>
    </body>
    </html>
    <?php
    exit;
}

/* ============================================================
   EKSPOR WORD (.doc berbasis HTML, dibuka native oleh MS Word)
   ============================================================ */
if ($format === 'word') {
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nama_berkas_dasar . '.doc"');
    header('Cache-Control: max-age=0');

    ob_start();
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml>
    <![endif]-->
    <title>Laporan Pengujian Sistem - Rotama Jaya</title>
    <style>
      body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #2A1315; margin: 0; background: #f9f5f1; }
      .dokumen { padding: 18pt 20pt 28pt; }
      .kepala { padding: 0 0 12pt; border-bottom: 3pt solid #6B1E2B; }
      .kepala table { margin:0; border:0; }
      .kepala td { border:0; padding:0 10pt 0 0; text-align:left; vertical-align:middle; }
      .kepala img { width:52pt; height:52pt; }
      .kepala h1 { margin: 0; font-size: 18pt; color: #6B1E2B; }
      .sub { margin-top: 4pt; font-size: 10pt; color: #6e5d57; }
      h2 { color: #6B1E2B; font-size: 13pt; margin: 20pt 0 8pt; border-bottom: 1pt solid #C9962F; padding-bottom: 4pt; }
      .ringkas { width: 100%; border-collapse: collapse; margin-top: 8pt; }
      .ringkas th, .ringkas td { border: 1pt solid #d9c5ac; padding: 6pt 8pt; font-size: 9.5pt; text-align: center; }
      .ringkas th { background: #6B1E2B; color: #ffffff; }
      .ringkas td { background: #ffffff; }
      table { border-collapse: collapse; width: 100%; margin-top: 8pt; }
      th, td { border: 1pt solid #d9c5ac; padding: 5pt 7pt; font-size: 9.5pt; text-align: center; }
      th { background: #FBF3E7; color: #2A1315; }
      .catatan { font-size: 9pt; color: #7A6A5F; margin-top: 12pt; }
      .keterangan { font-size: 9pt; color: #6e5d57; margin-top: 6pt; }
    </style>
    </head>
    <body>
      <div class="dokumen">
        <div class="kepala">
          <table><tr>
            <td><img src="<?= htmlspecialchars(BASE_URL . '/assets/img/logo_rotama_jaya.png', ENT_QUOTES, 'UTF-8') ?>" alt="Logo Rotama Jaya"></td>
            <td><h1>ROTAMA JAYA</h1><div class="sub">Percetakan dan Sistem Rekomendasi Desain Undangan</div><div class="sub">Laporan pengujian CNN MobileNetV2 dan TF-IDF + Kesamaan Kosinus</div><div class="sub">Dicetak pada: <?= htmlspecialchars($tanggal_cetak) ?></div></td>
          </tr></table>
        </div>

        <h2>Ringkasan Rata-Rata Metrik</h2>
        <table class="ringkas">
          <tr>
            <th>Metode</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th>
          </tr>
          <tr>
            <td>CNN MobileNetV2</td>
            <td><?= number_format($rerata_cnn['presisi_5'], 4) ?></td>
            <td><?= number_format($rerata_cnn['presisi_10'], 4) ?></td>
            <td><?= number_format($rerata_cnn['ingatan_5'], 4) ?></td>
            <td><?= number_format($rerata_cnn['ingatan_10'], 4) ?></td>
            <td><?= number_format($rerata_cnn['skor_f1_5'], 4) ?></td>
            <td><?= number_format($rerata_cnn['skor_f1_10'], 4) ?></td>
          </tr>
          <tr>
            <td>TF-IDF + Kesamaan Kosinus</td>
            <td><?= number_format($rerata_tfidf['presisi_5'], 4) ?></td>
            <td><?= number_format($rerata_tfidf['presisi_10'], 4) ?></td>
            <td><?= number_format($rerata_tfidf['ingatan_5'], 4) ?></td>
            <td><?= number_format($rerata_tfidf['ingatan_10'], 4) ?></td>
            <td><?= number_format($rerata_tfidf['skor_f1_5'], 4) ?></td>
            <td><?= number_format($rerata_tfidf['skor_f1_10'], 4) ?></td>
          </tr>
        </table>

        <h2>1. Pengujian Metode CNN MobileNetV2 (<?= count($data_cnn) ?> skenario)</h2>
        <table>
          <tr><th>Skenario</th><th>Gambar Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th></tr>
          <?php if ($data_cnn): foreach ($data_cnn as $b): ?>
          <tr>
            <td><?= (int) $b['nomor_skenario'] ?></td>
            <td><?= htmlspecialchars($b['nama_gambar_uji']) ?></td>
            <td><?= number_format((float) $b['presisi_5'], 4) ?></td>
            <td><?= number_format((float) $b['presisi_10'], 4) ?></td>
            <td><?= number_format((float) $b['ingatan_5'], 4) ?></td>
            <td><?= number_format((float) $b['ingatan_10'], 4) ?></td>
            <td><?= number_format((float) $b['skor_f1_5'], 4) ?></td>
            <td><?= number_format((float) $b['skor_f1_10'], 4) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8">Belum ada data pengujian CNN.</td></tr>
          <?php endif; ?>
        </table>

        <h2>2. Pengujian Metode TF-IDF + Kesamaan Kosinus (<?= count($data_tfidf) ?> skenario)</h2>
        <table>
          <tr><th>Skenario</th><th>Kata Kunci Uji</th><th>Presisi@5</th><th>Presisi@10</th><th>Ingatan@5</th><th>Ingatan@10</th><th>Skor-F1@5</th><th>Skor-F1@10</th></tr>
          <?php if ($data_tfidf): foreach ($data_tfidf as $b): ?>
          <tr>
            <td><?= (int) $b['nomor_skenario'] ?></td>
            <td><?= htmlspecialchars($b['kata_kunci_uji']) ?></td>
            <td><?= number_format((float) $b['presisi_5'], 4) ?></td>
            <td><?= number_format((float) $b['presisi_10'], 4) ?></td>
            <td><?= number_format((float) $b['ingatan_5'], 4) ?></td>
            <td><?= number_format((float) $b['ingatan_10'], 4) ?></td>
            <td><?= number_format((float) $b['skor_f1_5'], 4) ?></td>
            <td><?= number_format((float) $b['skor_f1_10'], 4) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8">Belum ada data pengujian TF-IDF.</td></tr>
          <?php endif; ?>
        </table>

        <p class="keterangan">Dokumen ini dibuat otomatis oleh Sistem Rekomendasi Desain Undangan Rotama Jaya untuk kebutuhan admin dan laporan penelitian.</p>
      </div>
    </body>
    </html>
    <?php
    echo ob_get_clean();
    exit;
}

/* ============================================================
   EKSPOR PDF (dibuat memakai pembuat PDF minimal, tanpa pustaka pihak ketiga)
   ============================================================ */
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nama_berkas_dasar . '.pdf"');
header('Cache-Control: max-age=0');

$pdf = new PdfSederhana();
$pdf->tambahHalaman();
$y = $pdf->mulaiY;

$pdf->persegiIsi(50, 780, 495, 3, '6B1E2B');
$pdf->persegiIsi(50, 776, 495, 1, 'C9962F');
$pdf->tulisBaris(75, $y, 'ROTAMA JAYA', true, 16);
$pdf->tulisBaris(75, $y, 'Percetakan dan Sistem Rekomendasi Desain Undangan', false, 9);
$pdf->tulisBaris(75, $y, 'Laporan pengujian CNN MobileNetV2 dan TF-IDF + Kesamaan Kosinus', false, 9);
$pdf->tulisBaris(75, $y, 'Dicetak pada: ' . $tanggal_cetak, false, 9);
$pdf->garis(50, $y, 545, $y, 1);
$y -= 12;

$pdf->tulisBaris(50, $y, 'Ringkasan Rata-Rata Metrik', true, 12);
$y -= 4;

$header_ringkas = sprintf('%-28s %-10s %-10s %-10s %-10s %-10s %-10s', 'Metode', 'Pre@5', 'Pre@10', 'Ing@5', 'Ing@10', 'F1@5', 'F1@10');
$pdf->tulisBarisMono(50, $y, $header_ringkas, 8.5);
$pdf->tulisBarisMono(50, $y, sprintf(
    '%-28s %-10s %-10s %-10s %-10s %-10s %-10s',
    'CNN MobileNetV2',
    number_format($rerata_cnn['presisi_5'], 4), number_format($rerata_cnn['presisi_10'], 4),
    number_format($rerata_cnn['ingatan_5'], 4), number_format($rerata_cnn['ingatan_10'], 4),
    number_format($rerata_cnn['skor_f1_5'], 4), number_format($rerata_cnn['skor_f1_10'], 4)
), 8.5);
$pdf->tulisBarisMono(50, $y, sprintf(
    '%-28s %-10s %-10s %-10s %-10s %-10s %-10s',
    'TF-IDF + KS',
    number_format($rerata_tfidf['presisi_5'], 4), number_format($rerata_tfidf['presisi_10'], 4),
    number_format($rerata_tfidf['ingatan_5'], 4), number_format($rerata_tfidf['ingatan_10'], 4),
    number_format($rerata_tfidf['skor_f1_5'], 4), number_format($rerata_tfidf['skor_f1_10'], 4)
), 8.5);
$y -= 18;

function tulis_tabel_pdf(PdfSederhana $pdf, float &$y, string $judul, array $baris, string $label_kolom_dua) {
    $pdf->tulisBaris(50, $y, $judul, true, 11);
    $y -= 4;

    $header = sprintf('%-10s %-30s %-9s %-10s %-9s %-10s %-8s %-8s', 'Sken.', $label_kolom_dua, 'Pre@5', 'Pre@10', 'Ing@5', 'Ing@10', 'F1@5', 'F1@10');
    $pdf->tulisBarisMono(50, $y, $header, 7.8);

    if (!$baris) {
        $pdf->tulisBaris(50, $y, 'Belum ada data pengujian.', false, 8.5);
        return;
    }

    foreach ($baris as $b) {
        $label = $b['nama_gambar_uji'] ?? ($b['kata_kunci_uji'] ?? '-');
        if (strlen($label) > 28) { $label = substr($label, 0, 25) . '...'; }
        $baris_teks = sprintf(
            '%-10s %-30s %-9s %-10s %-9s %-10s %-8s %-8s',
            $b['nomor_skenario'], $label,
            number_format((float) $b['presisi_5'], 3), number_format((float) $b['presisi_10'], 3),
            number_format((float) $b['ingatan_5'], 3), number_format((float) $b['ingatan_10'], 3),
            number_format((float) $b['skor_f1_5'], 3), number_format((float) $b['skor_f1_10'], 3)
        );
        $pdf->tulisBarisMono(50, $y, $baris_teks, 7.8);
    }

    $y -= 10;
}

tulis_tabel_pdf($pdf, $y, '1. Pengujian Metode CNN MobileNetV2 (' . count($data_cnn) . ' skenario)', $data_cnn, 'Gambar Uji');
tulis_tabel_pdf($pdf, $y, '2. Pengujian Metode TF-IDF + Kesamaan Kosinus (' . count($data_tfidf) . ' skenario)', $data_tfidf, 'Kata Kunci Uji');

$pdf->tulisBaris(50, $y, 'Dokumen dibuat otomatis oleh Sistem Rekomendasi Desain Undangan Rotama Jaya.', false, 8);

echo $pdf->keluarkan();
exit;

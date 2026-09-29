<?php
/**
 * TAMPILAN_HASIL_REKOMENDASI.PHP
 * Partial tampilan hasil rekomendasi (sidebar filter + daftar hasil terurut),
 * sesuai rancangan antarmuka Gambar 4.8 pada skripsi (Bab 4.3).
 * Dipakai bersama oleh cari_teks.php (jalur TF-IDF) dan cari_gambar.php (jalur CNN).
 *
 * Variabel yang harus disiapkan sebelum include:
 *   $jenis_jalur        : 'teks' | 'gambar'
 *   $label_kueri        : teks yang ditampilkan sebagai judul kueri pencarian
 *   $label_metode       : 'TF-IDF' atau 'CNN MobileNetV2'
 *   $hasil_tersaring    : array hasil (sudah difilter & terurut skor tertinggi)
 *   $jumlah_sebelum_filter : jumlah hasil SEBELUM filter diterapkan
 *   $daftar_kategori    : array baris tabel kategori_desain
 *   $kategori_terpilih  : array id_kategori yang sedang dicentang (string)
 *   $tema_terpilih      : array kode tema yang sedang dicentang (string)
 *   $skor_minimum_persen: int 0-100
 *   $ada_pencarian      : bool, apakah sudah ada kueri yang dijalankan
 *   $jumlah_hasil_dipilih : int, jumlah hasil yang akan ditampilkan (5 atau 10)
 */
$jumlah_hasil_dipilih = $jumlah_hasil_dipilih ?? 10;
$jumlah_hasil_dipilih = in_array($jumlah_hasil_dipilih, [5, 10]) ? $jumlah_hasil_dipilih : 10;
$daftar_tema_semua = daftar_tema_desain();
?>
<style>
  .tata-letak-hasil { display: grid; grid-template-columns: 260px 1fr; gap: 26px; max-width: 1100px; margin: 0 auto; padding: 32px 24px 60px; align-items: start; }
  @media (max-width: 820px) { .tata-letak-hasil { grid-template-columns: 1fr; } }

  .panel-filter { background: #fff; border: 1px solid var(--warna-batas); border-radius: var(--radius-lembut); padding: 20px; position: sticky; top: 18px; }
  .panel-filter h3 { margin: 0 0 14px; font-size: 15px; color: var(--warna-teks-utama); display: flex; align-items: center; gap: 6px; }
  .kelompok-filter { margin-bottom: 18px; }
  .kelompok-filter .label-kelompok { font-size: 11px; font-weight: 700; letter-spacing: .5px; color: var(--warna-teks-sekunder); text-transform: uppercase; margin-bottom: 8px; display:block; }
  .baris-cek { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--warna-teks-utama); margin-bottom: 7px; }
  .baris-cek input { accent-color: var(--warna-utama); }
  .baris-slider { display: flex; align-items: center; gap: 10px; }
  .baris-slider input[type=range] { flex: 1; accent-color: var(--warna-utama); }
  .nilai-slider { font-size: 13px; font-weight: 700; color: var(--warna-utama); min-width: 38px; text-align: right; }

  .header-hasil { margin-bottom: 18px; }
  .header-hasil h2 { margin: 0 0 4px; font-size: 19px; color: var(--warna-teks-utama); }
  .header-hasil p { margin: 0; font-size: 13px; color: var(--warna-teks-sekunder); }

  .kartu-hasil { display: flex; gap: 16px; padding: 16px; margin-bottom: 16px; align-items: center; }
  .kartu-hasil img { width: 96px; height: 96px; object-fit: cover; border-radius: 12px; flex-shrink: 0; }
  .kartu-hasil .info-hasil { flex: 1; min-width: 0; }
  .lencana-peringkat { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .4px; color: var(--warna-aksen-emas); margin-bottom: 4px; }
  .kartu-hasil h4 { margin: 0 0 4px; font-size: 15.5px; color: var(--warna-teks-utama); }
  .meta-hasil { font-size: 12.5px; color: var(--warna-teks-sekunder); margin-bottom: 10px; }
  .batang-skor-track { background: var(--warna-latar-alt); border-radius: 999px; height: 8px; width: 100%; overflow: hidden; margin-bottom: 4px; }
  .batang-skor-isi { background: linear-gradient(135deg, var(--warna-aksen-emas), var(--warna-utama)); height: 100%; border-radius: 999px; }
  .persen-skor { font-size: 12px; font-weight: 700; color: var(--warna-utama); }
  .aksi-hasil { display: flex; gap: 8px; margin-top: 10px; }
  .aksi-hasil a { font-size: 12.5px; padding: 8px 14px; border-radius: 999px; text-decoration: none; font-weight: 600; }
  .aksi-lihat { border: 1px solid var(--warna-batas); color: var(--warna-teks-utama); }
  .aksi-pesan { background: var(--warna-utama); color: #fff; }

  @media (max-width: 560px) {
    .kartu-hasil { flex-direction: column; align-items: flex-start; }
    .kartu-hasil img { width: 100%; height: 160px; }
  }
</style>

<div class="tata-letak-hasil">
  <!-- ===== PANEL FILTER (kiri) — sesuai Gambar 4.8 ===== -->
  <aside class="panel-filter">
    <h3>🔎 Filter Hasil</h3>
    <form method="GET" action="<?= BASE_URL ?>/cari_<?= $jenis_jalur ?>.php">
      <?php if ($jenis_jalur === 'teks'): ?>
        <input type="hidden" name="kata_kunci" value="<?= htmlspecialchars($label_kueri) ?>">
      <?php endif; ?>
      <input type="hidden" name="jumlah_hasil" value="<?= $jumlah_hasil_dipilih ?>">

      <div class="kelompok-filter">
        <span class="label-kelompok">Kategori</span>
        <?php foreach ($daftar_kategori as $k): ?>
          <label class="baris-cek">
            <input type="checkbox" name="kategori[]" value="<?= $k['id_kategori'] ?>"
              <?= in_array((string) $k['id_kategori'], $kategori_terpilih, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($k['nama_kategori']) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="kelompok-filter">
        <span class="label-kelompok">Tema Desain</span>
        <?php foreach ($daftar_tema_semua as $kode => $label): ?>
          <label class="baris-cek">
            <input type="checkbox" name="tema[]" value="<?= $kode ?>"
              <?= in_array($kode, $tema_terpilih, true) ? 'checked' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="kelompok-filter">
        <span class="label-kelompok">Skor Minimum</span>
        <div class="baris-slider">
          <input type="range" name="skor_min" min="0" max="100" step="5" value="<?= $skor_minimum_persen ?>"
            oninput="document.getElementById('nilaiSkorMin').textContent = this.value + '%'">
          <span class="nilai-slider" id="nilaiSkorMin"><?= $skor_minimum_persen ?>%</span>
        </div>
      </div>

      <button type="submit" class="tombol-utama" style="width:100%;">Terapkan Filter</button>
    </form>
  </aside>

  <!-- ===== DAFTAR HASIL (kanan) — sesuai Gambar 4.8 ===== -->
  <div>
    <?php if (!$ada_pencarian): ?>
      <div class="header-hasil">
        <h2>Belum ada pencarian</h2>
        <p>Masukkan <?= $jenis_jalur === 'teks' ? 'kata kunci' : 'gambar referensi' ?> di atas untuk melihat hasil rekomendasi.</p>
      </div>
    <?php else: ?>
  <div class="header-hasil">
    <h2>Hasil Rekomendasi: &ldquo;<?= htmlspecialchars($label_kueri) ?>&rdquo;</h2>
    <p>
      Ditemukan <?= count($hasil_tersaring) ?> dari <?= $jumlah_sebelum_filter ?> desain cocok
      &mdash; diurutkan berdasarkan skor kemiripan tertinggi (<?= htmlspecialchars($label_metode) ?>)
      <?php if (count($hasil_tersaring) > $jumlah_hasil_dipilih): ?>
        <br><small>Menampilkan Top-<?= $jumlah_hasil_dipilih ?> dari <?= count($hasil_tersaring) ?> hasil</small>
      <?php endif; ?>
    </p>
    <div style="margin-top:10px;">
      <span style="font-size:12px;color:var(--warna-teks-sekunder);margin-right:6px;">Tampilkan:</span>
      <button type="button" class="tombol-<?= $jumlah_hasil_dipilih === 5 ? 'utama' : 'garis' ?>" onclick="aturJumlahHasil(5)" style="padding:6px 14px;font-size:12px;">Top-5</button>
      <button type="button" class="tombol-<?= $jumlah_hasil_dipilih === 10 ? 'utama' : 'garis' ?>" onclick="aturJumlahHasil(10)" style="padding:6px 14px;font-size:12px;">Top-10</button>
    </div>
  </div>

  <script>
  function aturJumlahHasil(n) {
    const url = new URL(window.location.href);
    url.searchParams.set('jumlah_hasil', n);
    window.location.href = url.toString();
  }
  </script>

      <?php if (!$hasil_tersaring): ?>
        <div class="pesan-admin gagal" style="background:#fef2f2;color:#b91c1c;padding:14px 18px;border-radius:10px;">
          Tidak ada desain yang cocok dengan filter saat ini. Coba longgarkan filter kategori/tema atau turunkan skor minimum.
        </div>
      <?php else:
        $hasil_tampil = array_slice($hasil_tersaring, 0, $jumlah_hasil_dipilih);
        foreach ($hasil_tampil as $peringkat => $item): $persen = round($item['skor_kemiripan'] * 100, 1); ?>
        <div class="kartu-melayang kartu-hasil">
          <img src="<?= htmlspecialchars(url_gambar_desain($item['nama_file_gambar'], $item['nama_file_webp'] ?? null)) ?>" alt="<?= htmlspecialchars($item['judul_desain']) ?>" loading="lazy" decoding="async">
          <div class="info-hasil">
            <span class="lencana-peringkat">
              🏅 HASIL #<?= $peringkat + 1 ?><?= $peringkat === 0 ? ' — PALING RELEVAN' : '' ?>
            </span>
            <h4><?= htmlspecialchars($item['judul_desain']) ?></h4>
            <div class="meta-hasil">
              <?= htmlspecialchars($item['nama_kategori'] ?? '—') ?>
              <?php if ($item['tema']): ?> &middot; Tema: <?= htmlspecialchars($daftar_tema_semua[$item['tema']] ?? $item['tema']) ?><?php endif; ?>
            </div>
            <div class="batang-skor-track"><div class="batang-skor-isi" style="width:<?= $persen ?>%;"></div></div>
            <span class="persen-skor"><?= $persen ?>% kemiripan</span>
            <div class="aksi-hasil">
              <a class="aksi-lihat" href="<?= BASE_URL ?>/detail_desain.php?id=<?= $item['id_desain'] ?>&skor=<?= $item['skor_kemiripan'] ?>">Lihat Detail</a>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    <?php endif; ?>
  </div>
</div>

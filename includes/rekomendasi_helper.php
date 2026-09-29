<?php
/**
 * REKOMENDASI_HELPER.PHP
 * Fungsi-fungsi bersama yang dipakai oleh cari_teks.php dan cari_gambar.php,
 * supaya kedua jalur pencarian (non-hybrid, lihat Bab 3.2 & 3.8 skripsi) tetap
 * konsisten: memanggil layanan Python, memperkaya hasil dengan label kategori/tema,
 * dan menerapkan filter pada halaman hasil rekomendasi (Gambar 4.8 skripsi).
 */

/** Memanggil endpoint pencarian berbasis TEKS (TF-IDF) pada layanan Python Flask. */
function panggil_layanan_teks(string $kata_kunci): ?array
{
    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/cari_berdasarkan_teks');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode(['kata_kunci' => $kata_kunci]));
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
    $tanggapan = curl_exec($curl);
    $kode_http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($tanggapan === false) {
        return null;
    }
    $data = json_decode($tanggapan, true);
    if ($kode_http >= 400) {
        return ['hasil' => [], 'kesalahan' => $data['kesalahan'] ?? 'Layanan TF-IDF mengembalikan kesalahan.'];
    }
    return is_array($data) ? $data : ['hasil' => [], 'kesalahan' => 'Respons layanan TF-IDF tidak valid.'];
}

/** Memanggil endpoint pencarian berbasis GAMBAR (CNN MobileNetV2) pada layanan Python Flask. */
function panggil_layanan_gambar(string $path_file_lokal): ?array
{
    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/cari_berdasarkan_gambar');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, ['gambar' => new CURLFile($path_file_lokal)]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    $tanggapan = curl_exec($curl);
    curl_close($curl);
    if ($tanggapan === false) {
        return null;
    }
    return json_decode($tanggapan, true) ?: ['hasil' => []];
}

/**
 * Memperkaya hasil mentah dari layanan Python (id_desain, judul_desain,
 * nama_file_gambar, skor_kemiripan) dengan label kategori & tema dari basis data,
 * supaya bisa dipakai sebagai bahan filter di halaman hasil (Gambar 4.8).
 * Urutan hasil (berdasarkan skor tertinggi) tetap dipertahankan.
 */
function perkaya_hasil_rekomendasi(PDO $koneksi, array $hasil_mentah): array
{
    if (!$hasil_mentah) {
        return [];
    }
    $ids = array_column($hasil_mentah, 'id_desain');
    $placeholder = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $koneksi->prepare(
        "SELECT d.id_desain, d.id_kategori, d.tema, d.deskripsi_desain, d.nama_file_webp, k.nama_kategori
         FROM desain_undangan d
         LEFT JOIN kategori_desain k ON k.id_kategori = d.id_kategori
         WHERE d.id_desain IN ($placeholder)"
    );
    $stmt->execute($ids);
    $label = [];
    foreach ($stmt->fetchAll() as $b) {
        $label[$b['id_desain']] = $b;
    }

    $hasil_kaya = [];
    foreach ($hasil_mentah as $item) {
        $tambahan = $label[$item['id_desain']] ?? [];
        $hasil_kaya[] = array_merge($item, [
            'id_kategori'     => $tambahan['id_kategori'] ?? null,
            'nama_kategori'   => $tambahan['nama_kategori'] ?? null,
            'tema'            => $tambahan['tema'] ?? null,
            'deskripsi_desain' => $tambahan['deskripsi_desain'] ?? '',
            'nama_file_webp'   => $tambahan['nama_file_webp'] ?? null,
        ]);
    }
    return $hasil_kaya;
}

/**
 * Menerapkan filter kategori, tema, dan skor minimum pada hasil yang sudah diperkaya.
 * Filter kategori/tema kosong berarti "tampilkan semua" (tidak membatasi).
 */
function terapkan_filter_hasil(array $hasil_kaya, array $kategori_terpilih, array $tema_terpilih, float $skor_minimum): array
{
    return array_values(array_filter($hasil_kaya, function ($item) use ($kategori_terpilih, $tema_terpilih, $skor_minimum) {
        if ($kategori_terpilih && !in_array((string) $item['id_kategori'], $kategori_terpilih, true)) {
            return false;
        }
        if ($tema_terpilih && !in_array((string) $item['tema'], $tema_terpilih, true)) {
            return false;
        }
        if ((float) $item['skor_kemiripan'] < $skor_minimum) {
            return false;
        }
        return true;
    }));
}

/** Daftar tema desain sesuai batasan masalah penelitian (sub-bab 1.4 skripsi). */
function daftar_tema_desain(): array
{
    return [
        'elegan'     => 'Elegan',
        'floral'     => 'Floral',
        'modern'     => 'Modern',
        'minimalis'  => 'Minimalis',
        'islami'     => 'Islami',
        'adat_batak' => 'Adat Batak',
    ];
}

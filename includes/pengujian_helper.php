<?php
/**
 * PENGUJIAN_HELPER.PHP
 * Fungsi bersama untuk halaman Laporan Pengujian CNN (laporan_pengujian_cnn.php)
 * dan Laporan Pengujian TF-IDF (laporan_pengujian_tfidf.php), supaya logika
 * penghitungan metrik & evaluasi massal tidak dobel di kedua halaman.
 */

/**
 * Menghitung Precision@K, Recall@K, F1-Score@K sesuai Bab 3.9 skripsi:
 * relevan = kategori DAN tema hasil pencarian sama persis dengan kategori/tema
 * yang ditentukan untuk skenario uji (truth berbasis label, bukan penilaian manusia).
 *
 * PENTING: Query (id_desain_terkecuali) HARUS sudah dikeluarkan dari $hasil_urut
 * SEBELUM fungsi ini dipanggil. Fungsi ini hanya menghitung metrik dari hasil
 * yang sudah difilter.
 */
function hitung_metrik_evaluasi(array $hasil_urut, int $id_kategori_uji, string $tema_uji, PDO $koneksi, ?int $id_desain_terkecuali = null): array {
    // |Relevan| = jumlah SELURUH desain di dataset dengan kategori & tema yang sama persis
    // Karena query sudah dikeluarkan dari $hasil_urut di pemanggil, tidak perlu exclude lagi di sini
    $stmt = $koneksi->prepare("SELECT COUNT(*) FROM desain_undangan WHERE id_kategori = ? AND tema = ?");
    $stmt->execute([$id_kategori_uji, $tema_uji]);
    $jumlah_relevan_total = (int) $stmt->fetchColumn();

    // Ambil label kategori & tema setiap desain dalam hasil pencarian (urut sesuai skor)
    $ids = array_column($hasil_urut, 'id_desain');
    $label_per_desain = [];
    if ($ids) {
        $placeholder = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $koneksi->prepare("SELECT id_desain, id_kategori, tema FROM desain_undangan WHERE id_desain IN ($placeholder)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $b) {
            $label_per_desain[$b['id_desain']] = $b;
        }
    }

    // Hitung berapa yang relevan dalam Top-5 dan Top-10
    $relevan_5 = 0;
    foreach (array_slice($hasil_urut, 0, 5) as $item) {
        $label = $label_per_desain[$item['id_desain']] ?? null;
        if ($label && (int) $label['id_kategori'] === $id_kategori_uji && $label['tema'] === $tema_uji) {
            $relevan_5++;
        }
    }
    
    $relevan_10 = 0;
    foreach (array_slice($hasil_urut, 0, 10) as $item) {
        $label = $label_per_desain[$item['id_desain']] ?? null;
        if ($label && (int) $label['id_kategori'] === $id_kategori_uji && $label['tema'] === $tema_uji) {
            $relevan_10++;
        }
    }

    $presisi_5 = $relevan_5 / 5;
    $presisi_10 = $relevan_10 / 10;
    $ingatan_5 = $jumlah_relevan_total > 0 ? $relevan_5 / $jumlah_relevan_total : 0;
    $ingatan_10 = $jumlah_relevan_total > 0 ? $relevan_10 / $jumlah_relevan_total : 0;
    $f1_5 = ($presisi_5 + $ingatan_5) > 0 ? 2 * $presisi_5 * $ingatan_5 / ($presisi_5 + $ingatan_5) : 0;
    $f1_10 = ($presisi_10 + $ingatan_10) > 0 ? 2 * $presisi_10 * $ingatan_10 / ($presisi_10 + $ingatan_10) : 0;

    return [$presisi_5, $presisi_10, $ingatan_5, $ingatan_10, $f1_5, $f1_10];
}

/** Nomor skenario berikutnya (1, 2, 3, ...) untuk sebuah tabel pengujian. */
function nomor_skenario_berikutnya(PDO $koneksi, string $tabel): int {
    return (int) $koneksi->query("SELECT COALESCE(MAX(nomor_skenario), 0) + 1 FROM `$tabel`")->fetchColumn();
}

function normalisasi_vektor_php($vektor): ?array {
    if (is_string($vektor)) {
        $terurai = json_decode($vektor, true);
        if (is_array($terurai)) {
            $vektor = $terurai;
        } else {
            return null;
        }
    }

    if (!is_array($vektor)) {
        return null;
    }

    $hasil = [];
    foreach ($vektor as $nilai) {
        $hasil[] = (float) $nilai;
    }
    return $hasil ?: null;
}

function kesamaan_kosinus_php($vektor_a, $vektor_b): float {
    $a = normalisasi_vektor_php($vektor_a);
    $b = normalisasi_vektor_php($vektor_b);
    if (!$a || !$b || count($a) !== count($b)) {
        return 0.0;
    }

    $dot = 0.0;
    $norm_a = 0.0;
    $norm_b = 0.0;
    foreach ($a as $index => $nilai_a) {
        $nilai_b = $b[$index] ?? 0.0;
        $dot += $nilai_a * $nilai_b;
        $norm_a += $nilai_a * $nilai_a;
        $norm_b += $nilai_b * $nilai_b;
    }

    if ($norm_a == 0.0 || $norm_b == 0.0) {
        return 0.0;
    }

    return (float) ($dot / (sqrt($norm_a) * sqrt($norm_b)));
}

/**
 * Evaluasi massal fallback murni-PHP (dipakai kalau layanan Python tidak bisa
 * dihubungi saat "Jalankan Semua Skenario Otomatis" diklik). Mengembalikan
 * ['cnn' => [...], 'tfidf' => [...]] — tiap halaman memakai bagian miliknya saja.
 */
function evaluasi_massal_php(PDO $koneksi): array {
    $daftar_desain = $koneksi->query(
        "SELECT id_desain, nama_file_gambar, kata_kunci, id_kategori, tema, vektor_fitur_cnn, vektor_fitur_tfidf
         FROM desain_undangan
         WHERE id_kategori IS NOT NULL AND tema IS NOT NULL
         ORDER BY id_desain"
    )->fetchAll();

    $hasil_cnn = [];
    $hasil_tfidf = [];

    foreach ($daftar_desain as $item) {
        $vektor_cnn = normalisasi_vektor_php($item['vektor_fitur_cnn'] ?? null);
        $vektor_tfidf = normalisasi_vektor_php($item['vektor_fitur_tfidf'] ?? null);

        if ($vektor_cnn) {
            $hasil = [];
            foreach ($daftar_desain as $desain) {
                $vektor_lawan = normalisasi_vektor_php($desain['vektor_fitur_cnn'] ?? null);
                if (!$vektor_lawan) {
                    continue;
                }
                $hasil[] = [
                    'id_desain' => (int) $desain['id_desain'],
                    'skor_kemiripan' => kesamaan_kosinus_php($vektor_cnn, $vektor_lawan),
                ];
            }
            // EXCLUDE QUERY SEBELUM SORT & TOP-10
            $hasil = array_filter($hasil, fn($i) => $i['id_desain'] !== (int) $item['id_desain']);
            usort($hasil, fn($a, $b) => $b['skor_kemiripan'] <=> $a['skor_kemiripan']);
            $hasil = array_slice($hasil, 0, 10);
            [$p5, $p10, $i5, $i10, $f5, $f10] = hitung_metrik_evaluasi($hasil, (int) $item['id_kategori'], (string) $item['tema'], $koneksi);
            $hasil_cnn[] = [
                'nomor_skenario' => 0,
                'nama_gambar_uji' => (string) $item['nama_file_gambar'],
                'presisi_5' => $p5, 'presisi_10' => $p10,
                'ingatan_5' => $i5, 'ingatan_10' => $i10,
                'skor_f1_5' => $f5, 'skor_f1_10' => $f10,
            ];
        } else {
            $hasil_cnn[] = [
                'nomor_skenario' => 0, 'nama_gambar_uji' => (string) $item['nama_file_gambar'],
                'presisi_5' => 0, 'presisi_10' => 0, 'ingatan_5' => 0, 'ingatan_10' => 0,
                'skor_f1_5' => 0, 'skor_f1_10' => 0,
            ];
        }

        if ($vektor_tfidf) {
            $hasil = [];
            foreach ($daftar_desain as $desain) {
                $vektor_lawan = normalisasi_vektor_php($desain['vektor_fitur_tfidf'] ?? null);
                if (!$vektor_lawan) {
                    continue;
                }
                $hasil[] = [
                    'id_desain' => (int) $desain['id_desain'],
                    'skor_kemiripan' => kesamaan_kosinus_php($vektor_tfidf, $vektor_lawan),
                ];
            }
            // EXCLUDE QUERY SEBELUM SORT & TOP-10
            $hasil = array_filter($hasil, fn($i) => $i['id_desain'] !== (int) $item['id_desain']);
            usort($hasil, fn($a, $b) => $b['skor_kemiripan'] <=> $a['skor_kemiripan']);
            $hasil = array_slice($hasil, 0, 10);
            [$p5, $p10, $i5, $i10, $f5, $f10] = hitung_metrik_evaluasi($hasil, (int) $item['id_kategori'], (string) $item['tema'], $koneksi);
            $hasil_tfidf[] = [
                'nomor_skenario' => 0,
                'kata_kunci_uji' => (string) $item['kata_kunci'],
                'presisi_5' => $p5, 'presisi_10' => $p10,
                'ingatan_5' => $i5, 'ingatan_10' => $i10,
                'skor_f1_5' => $f5, 'skor_f1_10' => $f10,
            ];
        } else {
            $hasil_tfidf[] = [
                'nomor_skenario' => 0, 'kata_kunci_uji' => (string) $item['kata_kunci'],
                'presisi_5' => 0, 'presisi_10' => 0, 'ingatan_5' => 0, 'ingatan_10' => 0,
                'skor_f1_5' => 0, 'skor_f1_10' => 0,
            ];
        }
    }

    return ['cnn' => $hasil_cnn, 'tfidf' => $hasil_tfidf];
}

/**
 * Memanggil endpoint massal di layanan Python (dengan fallback PHP kalau layanan
 * mati), dan mengembalikan HANYA bagian ($jenis = 'cnn' atau 'tfidf') yang diminta
 * pemanggil. Dipakai oleh kedua halaman (CNN & TF-IDF) secara independen.
 */
function jalankan_evaluasi_massal(PDO $koneksi, string $jenis): array {
    $daftar_desain_uji = $koneksi->query(
        "SELECT id_desain, nama_file_gambar, kata_kunci, id_kategori, tema
         FROM desain_undangan
         WHERE id_kategori IS NOT NULL AND tema IS NOT NULL
         ORDER BY id_desain
         LIMIT 30"
    )->fetchAll();

    if (!$daftar_desain_uji) {
        return [];
    }

    $payload = ['daftar_desain' => array_map(function ($d) {
        return [
            'id_desain' => (int) $d['id_desain'],
            'nama_file_gambar' => (string) $d['nama_file_gambar'],
            'kata_kunci' => (string) $d['kata_kunci'],
            'id_kategori' => (int) $d['id_kategori'],
            'tema' => (string) $d['tema'],
        ];
    }, $daftar_desain_uji)];

    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/evaluasi_skenario_massal');
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 3000);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 30000); // dilonggarkan supaya aman kalau jumlah desain terus bertambah
    $tanggapan = curl_exec($curl);
    curl_close($curl);

    $hasil_cnn = [];
    $hasil_tfidf = [];
    if ($tanggapan !== false) {
        $data = json_decode($tanggapan, true);
        if (is_array($data)) {
            $hasil_cnn = $data['cnn'] ?? [];
            $hasil_tfidf = $data['tfidf'] ?? [];
        }
    }

    if (!$hasil_cnn && !$hasil_tfidf) {
        $hasil_massal = evaluasi_massal_php($koneksi);
        $hasil_cnn = $hasil_massal['cnn'] ?? [];
        $hasil_tfidf = $hasil_massal['tfidf'] ?? [];
    }

    return $jenis === 'cnn' ? $hasil_cnn : $hasil_tfidf;
}

/**
 * Impor satu berkas CSV upload ke tabel pengujian yang sesuai (jalur LEGACY/opsional,
 * dipakai hanya kalau sudah punya angka pengujian dari sumber lain di luar sistem ini).
 * INSERT ... ON DUPLICATE KEY UPDATE dipakai supaya berkas boleh diunggah ulang tanpa
 * membuat data dobel — baris dengan nomor_skenario yang sama akan diperbarui.
 */
function impor_csv_pengujian(PDO $koneksi, string $jenis, array $berkas_upload): array {
    $tmp = $berkas_upload['tmp_name'] ?? null;
    if (!$tmp || !is_uploaded_file($tmp)) {
        return [0, 0];
    }

    $handle = fopen($tmp, 'r');
    fgetcsv($handle); // lewati baris header
    $diimpor = 0;
    $dilewati = 0;

    if ($jenis === 'cnn') {
        $pernyataan = $koneksi->prepare(
            "INSERT INTO pengujian_cnn
            (nomor_skenario, nama_gambar_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nama_gambar_uji = VALUES(nama_gambar_uji), presisi_5 = VALUES(presisi_5),
                presisi_10 = VALUES(presisi_10), ingatan_5 = VALUES(ingatan_5),
                ingatan_10 = VALUES(ingatan_10), skor_f1_5 = VALUES(skor_f1_5), skor_f1_10 = VALUES(skor_f1_10)"
        );
        while (($baris = fgetcsv($handle)) !== false) {
            [$no, $nama, $p5, $p10, $i5, $i10, $f5, $f10] = array_pad($baris, 8, '');
            if ($p5 === '' || $p10 === '') { $dilewati++; continue; }
            $pernyataan->execute([$no, $nama, $p5, $p10, $i5, $i10, $f5, $f10]);
            $diimpor++;
        }
    } elseif ($jenis === 'tfidf') {
        $pernyataan = $koneksi->prepare(
            "INSERT INTO pengujian_tfidf
            (nomor_skenario, kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10, skor_f1_5, skor_f1_10)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                kata_kunci_uji = VALUES(kata_kunci_uji), presisi_5 = VALUES(presisi_5),
                presisi_10 = VALUES(presisi_10), ingatan_5 = VALUES(ingatan_5),
                ingatan_10 = VALUES(ingatan_10), skor_f1_5 = VALUES(skor_f1_5), skor_f1_10 = VALUES(skor_f1_10)"
        );
        while (($baris = fgetcsv($handle)) !== false) {
            [$no, $kata_kunci, $p5, $p10, $i5, $i10, $f5, $f10] = array_pad($baris, 8, '');
            if ($p5 === '' || $p10 === '') { $dilewati++; continue; }
            $pernyataan->execute([$no, $kata_kunci, $p5, $p10, $i5, $i10, $f5, $f10]);
            $diimpor++;
        }
    }

    fclose($handle);
    return [$diimpor, $dilewati];
}

/** Rata-rata satu kolom metrik dari kumpulan baris hasil pengujian. */
function rerata_metrik(array $baris, string $kolom): float {
    if (!$baris) return 0.0;
    return array_sum(array_column($baris, $kolom)) / count($baris);
}

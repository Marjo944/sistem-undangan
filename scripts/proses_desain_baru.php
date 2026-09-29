<?php
require_once __DIR__ . '/../koneksi_database.php';

$payload_json = $argv[1] ?? '';
if ($payload_json === '') {
    exit(1);
}

$payload = json_decode($payload_json, true);
if (!is_array($payload)) {
    exit(1);
}

$id_desain = (int) ($payload['id_desain'] ?? 0);
$judul = (string) ($payload['judul'] ?? '');
$kata_kunci = (string) ($payload['kata_kunci'] ?? '');
$nama_file_gambar = (string) ($payload['nama_file_gambar'] ?? '');
$nama_file_produksi = isset($payload['nama_file_produksi']) && $payload['nama_file_produksi'] !== null ? (string) $payload['nama_file_produksi'] : null;
$id_kategori = (int) ($payload['id_kategori'] ?? 0);
$tema = isset($payload['tema']) && $payload['tema'] !== null ? (string) $payload['tema'] : null;

if ($id_desain <= 0 || $nama_file_gambar === '') {
    exit(1);
}

$path_gambar = DIREKTORI_GAMBAR_DESAIN . $nama_file_gambar;
if (!is_file($path_gambar)) {
    exit(1);
}

$curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/ekstrak_fitur_unggahan');
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, [
    'gambar' => new CURLFile($path_gambar),
    'kata_kunci' => $kata_kunci,
]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 5000);
curl_setopt($curl, CURLOPT_TIMEOUT_MS, 120000);
$tanggapan = curl_exec($curl);
$kesalahan = curl_error($curl);
curl_close($curl);

if ($tanggapan === false) {
    $koneksi->prepare('UPDATE desain_undangan SET status_proses = ?, vektor_fitur_cnn = NULL, vektor_fitur_tfidf = NULL WHERE id_desain = ?')->execute(['gagal', $id_desain]);
    exit(1);
}

$data_fitur = json_decode($tanggapan, true);
if (!is_array($data_fitur)) {
    $koneksi->prepare('UPDATE desain_undangan SET status_proses = ?, vektor_fitur_cnn = NULL, vektor_fitur_tfidf = NULL WHERE id_desain = ?')->execute(['gagal', $id_desain]);
    exit(1);
}

$nama_file_webp = $data_fitur['nama_file_webp'] ?? null;
$vektor_cnn = json_encode($data_fitur['vektor_cnn'] ?? []);
$vektor_tfidf = json_encode($data_fitur['vektor_tfidf'] ?? []);

$koneksi->prepare(
    "UPDATE desain_undangan SET id_kategori = ?, tema = ?, judul_desain = ?, kata_kunci = ?, nama_file_webp = ?, nama_file_produksi = ?, status_proses = ?, vektor_fitur_cnn = ?, vektor_fitur_tfidf = ? WHERE id_desain = ?"
)->execute([
    $id_kategori,
    $tema,
    $judul,
    $kata_kunci,
    $nama_file_webp,
    $nama_file_produksi,
    'siap',
    $vektor_cnn,
    $vektor_tfidf,
    $id_desain,
]);

exit(0);

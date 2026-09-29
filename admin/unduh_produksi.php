<?php
/**
 * UNDUH_PRODUKSI.PHP
 * Mengunduh file produksi (PSD/CDR) sebuah desain.
 * HANYA bisa diakses oleh admin yang sedang login — pelanggan tidak pernah
 * diberi tahu lokasi file ini maupun diberi akses ke halaman ini.
 */
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$id_desain = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$pernyataan = $koneksi->prepare("SELECT judul_desain, nama_file_produksi FROM desain_undangan WHERE id_desain = ?");
$pernyataan->execute([$id_desain]);
$desain = $pernyataan->fetch();

if (!$desain || empty($desain['nama_file_produksi'])) {
    http_response_code(404);
    die('File produksi tidak ditemukan.');
}

$path_file = path_file_produksi($desain['nama_file_produksi']);

if ($path_file === null) {
    http_response_code(404);
    die('File produksi tidak ditemukan di server.');
}

$ekstensi = strtolower(pathinfo($path_file, PATHINFO_EXTENSION));
$nama_unduhan = preg_replace('/[^A-Za-z0-9_\-]/', '_', $desain['judul_desain']) . '.' . $ekstensi;

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nama_unduhan . '"');
header('Content-Length: ' . filesize($path_file));
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($path_file);
exit;

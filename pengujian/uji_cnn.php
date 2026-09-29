<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin(['admin']);

$_SESSION['evaluasi_internal'] = 'cnn';
header('Location: ' . BASE_URL . '/admin/laporan_pengujian_cnn.php?internal=1');
exit;

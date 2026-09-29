<?php
require_once __DIR__ . '/../koneksi_database.php';
unset($_SESSION['id_admin'], $_SESSION['nama_admin'], $_SESSION['peran']);
session_destroy();
// Setelah sesi admin dihapus, kembali ke beranda publik.
header('Location: ' . BASE_URL . '/beranda_publik.php');
exit;

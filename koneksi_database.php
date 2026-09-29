<?php
/**
 * KONEKSI_DATABASE.PHP
 * Sistem Rekomendasi Desain Undangan - Percetakan Rotama Jaya
 * Berisi koneksi PDO ke MySQL dan konfigurasi dasar sistem.
 */

function muat_berkas_env(string $path_env): void {
    if (!is_file($path_env)) {
        return;
    }
    foreach (file($path_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
        $baris = trim($baris);
        if ($baris === '' || str_starts_with($baris, '#') || !str_contains($baris, '=')) {
            continue;
        }
        [$kunci, $nilai] = explode('=', $baris, 2);
        $kunci = trim($kunci);
        $nilai = trim($nilai);
        if (strlen($nilai) >= 2 && (
            ($nilai[0] === '"' && $nilai[-1] === '"') ||
            ($nilai[0] === "'" && $nilai[-1] === "'")
        )) {
            $nilai = substr($nilai, 1, -1);
        }
        if (getenv($kunci) === false) {
            putenv("$kunci=$nilai");
        }
    }
}

muat_berkas_env(__DIR__ . '/.env');

function konfigurasi(string $kunci, string $nilai_bawaan = ''): string {
    $nilai = getenv($kunci);
    return $nilai === false ? $nilai_bawaan : $nilai;
}

define('APP_ENV', konfigurasi('APP_ENV', 'development'));
define('MODE_PRODUKSI', APP_ENV === 'production');

if (MODE_PRODUKSI) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ==== KONFIGURASI DASAR ====
define('HOST_DATABASE', konfigurasi('DB_HOST', 'localhost'));
define('NAMA_DATABASE', konfigurasi('DB_NAME', 'rotama_jaya'));
define('PENGGUNA_DATABASE', konfigurasi('DB_USER', 'root'));
define('SANDI_DATABASE', konfigurasi('DB_PASSWORD', ''));

// Alamat layanan Python Flask (mesin rekomendasi).
// Di server produksi, layanan ini tetap dipanggil lewat localhost/127.0.0.1
// (server-to-server, tidak diakses langsung oleh browser pengunjung),
// tapi tetap dibuat bisa diatur lewat .env kalau suatu saat dipisah ke host lain.
define('ALAMAT_LAYANAN_PYTHON', konfigurasi('ALAMAT_LAYANAN_PYTHON', 'http://127.0.0.1:5000'));

// Direktori unggahan
define('DIREKTORI_GAMBAR_DESAIN', __DIR__ . '/uploads/desain/');
define('DIREKTORI_FILE_PRODUKSI', __DIR__ . '/uploads/produksi/');

/**
 * BASE_URL — prefix alamat web menuju folder proyek ini, dideteksi otomatis.
 * Supaya seluruh link (/beranda.php, /assets/css/gaya.css, dll) tetap benar
 * baik saat folder rotama_jaya/ dijadikan document root LANGSUNG, maupun saat
 * hanya disalin sebagai subfolder (misal htdocs/rotama_jaya/ di XAMPP/Laragon).
 */
$folder_proyek_fs = rtrim(str_replace('\\', '/', __DIR__), '/');
$folder_root_web  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$prefix_base_url  = '';
if ($folder_root_web !== '' && strpos($folder_proyek_fs, $folder_root_web) === 0) {
    $prefix_base_url = substr($folder_proyek_fs, strlen($folder_root_web));
}
define('BASE_URL', $prefix_base_url); // contoh hasil: '' (di root) atau '/rotama_jaya' (di subfolder)

/**
 * URL aset statis berversi supaya cache browser dapat dipakai tanpa menahan
 * CSS/JS lama setelah file berubah saat deployment.
 */
function url_aset(string $path): string
{
    $path = ltrim($path, '/');
    $path_lokal = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $versi = is_file($path_lokal) ? (string) filemtime($path_lokal) : '1';
    return BASE_URL . '/' . $path . '?v=' . rawurlencode($versi);
}

/**
 * Menghasilkan URL pratinjau desain yang valid, termasuk untuk data lama yang
 * mungkin menyimpan path lengkap, atau saat berkas unggahan belum ikut disalin.
 * Memprioritaskan versi WebP terkompresi kalau tersedia untuk performa loading.
 */
function url_gambar_desain(?string $nama_file, ?string $nama_file_webp = null): string
{
    $nama_file = basename(trim((string) $nama_file));
    $nama_file_webp = $nama_file_webp ? basename(trim((string) $nama_file_webp)) : null;
    
    if ($nama_file_webp !== null && $nama_file_webp !== '' && is_file(DIREKTORI_GAMBAR_DESAIN . $nama_file_webp)) {
        return BASE_URL . '/uploads/desain/' . rawurlencode($nama_file_webp);
    }
    
    if ($nama_file !== '' && is_file(DIREKTORI_GAMBAR_DESAIN . $nama_file)) {
        return BASE_URL . '/uploads/desain/' . rawurlencode($nama_file);
    }

    return BASE_URL . '/assets/img/contoh_desain_1.jpg';
}

/**
 * Mengembalikan path absolut file produksi yang benar-benar berada di folder
 * unggahan produksi. Dipakai halaman admin agar file produksi dapat ditautkan
 * dari pesanan tanpa membuka celah traversal path.
 */
function path_file_produksi(?string $nama_file): ?string
{
    $nama_file = basename(str_replace('\\', '/', trim((string) $nama_file)));
    if ($nama_file === '') {
        return null;
    }

    $direktori = realpath(DIREKTORI_FILE_PRODUKSI);
    if ($direktori === false) {
        return null;
    }

    $path_file = realpath($direktori . DIRECTORY_SEPARATOR . $nama_file);
    $prefix_direktori = rtrim($direktori, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($path_file === false || !is_file($path_file)
        || strncmp($path_file, $prefix_direktori, strlen($prefix_direktori)) !== 0) {
        return null;
    }

    return $path_file;
}

// Pastikan folder unggahan selalu tersedia (dibuat otomatis kalau belum ada,
// supaya fitur unggah desain di admin tidak gagal hanya karena foldernya hilang)
foreach ([DIREKTORI_GAMBAR_DESAIN, DIREKTORI_FILE_PRODUKSI] as $direktori) {
    if (!is_dir($direktori)) {
        @mkdir($direktori, 0775, true);
    }
}

// Cookie sesi: httponly selalu aktif, dan "secure" otomatis aktif kalau
// diakses lewat HTTPS (wajib di server produksi supaya cookie tak bisa disadap).
$diakses_lewat_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? null) == 443
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => BASE_URL !== '' ? BASE_URL . '/' : '/',
    'secure' => $diakses_lewat_https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

try {
    $koneksi = new PDO(
        "mysql:host=" . HOST_DATABASE . ";dbname=" . NAMA_DATABASE . ";charset=utf8mb4",
        PENGGUNA_DATABASE,
        SANDI_DATABASE,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $kesalahan) {
    if (MODE_PRODUKSI) {
        error_log('Koneksi basis data gagal: ' . $kesalahan->getMessage());
        http_response_code(500);
        die('Maaf, sistem sedang tidak dapat diakses. Silakan coba lagi beberapa saat lagi.');
    }
    die("Koneksi basis data gagal: " . $kesalahan->getMessage());
}

/**
 * Memulihkan entri katalog minimum bila database pernah dibuat ulang sementara
 * berkas unggahan lama masih ada. Kejadian ini umum saat database/rotama_jaya_online.sql diimpor
 * kembali: gambar/CDR tetap berada di uploads, tetapi katalog kosong karena
 * metadata desain tersimpan di MySQL.
 *
 * Pemulihan hanya berjalan pada kondisi yang sangat spesifik dan aman:
 * kedua tabel katalog harus kosong dan setidaknya ada satu gambar desain.
 * File yang sudah dihapus lewat aplikasi tidak akan muncul kembali karena
 * aplikasi menghapus file bersamaan dengan record-nya. Judul hasil pemulihan
 * sengaja diberi penanda agar admin dapat mengganti metadata aslinya.
 */
function pulihkan_katalog_dari_unggahan(PDO $koneksi): void {
    try {
        $jumlah_kategori = (int) $koneksi->query('SELECT COUNT(*) FROM kategori_desain')->fetchColumn();
        $jumlah_desain = (int) $koneksi->query('SELECT COUNT(*) FROM desain_undangan')->fetchColumn();
        if ($jumlah_kategori !== 0 || $jumlah_desain !== 0) {
            return;
        }

        $gambar = array_values(array_filter(glob(DIREKTORI_GAMBAR_DESAIN . '*') ?: [], static function ($path) {
            return is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
        }));
        if ($gambar === []) {
            return;
        }
        usort($gambar, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        $produksi = array_values(array_filter(glob(DIREKTORI_FILE_PRODUKSI . '*') ?: [], static function ($path) {
            return is_file($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['psd', 'cdr', 'ai', 'pdf', 'zip', 'rar'], true);
        }));
        usort($produksi, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        $koneksi->beginTransaction();
        $koneksi->prepare('INSERT INTO kategori_desain (nama_kategori) VALUES (?)')->execute(['Koleksi Dipulihkan']);
        $id_kategori = (int) $koneksi->lastInsertId();
        $id_admin = $koneksi->query('SELECT id_admin FROM admin ORDER BY id_admin LIMIT 1')->fetchColumn() ?: null;
        $simpan = $koneksi->prepare(
            'INSERT INTO desain_undangan
             (id_kategori, judul_desain, deskripsi_desain, kata_kunci, nama_file_gambar, nama_file_produksi, vektor_fitur_cnn, vektor_fitur_tfidf, diunggah_oleh)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($gambar as $nomor => $path_gambar) {
            $waktu = date('d-m-Y H:i', filemtime($path_gambar));
            $simpan->execute([
                $id_kategori,
                'Desain dipulihkan ' . ($nomor + 1),
                'Entri ini dipulihkan otomatis dari berkas unggahan lama (' . $waktu . '). Silakan lengkapi judul, kategori, tema, dan kata kunci.',
                'desain undangan koleksi dipulihkan',
                basename($path_gambar),
                isset($produksi[$nomor]) ? basename($produksi[$nomor]) : null,
                '[]',
                '[]',
                $id_admin,
            ]);
        }
        $koneksi->commit();
    } catch (Throwable $kesalahan_pemulihan) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        error_log('Pemulihan katalog dari unggahan gagal: ' . $kesalahan_pemulihan->getMessage());
    }
}

/**
 * Memeriksa apakah pengguna sedang login dengan peran yang diizinkan.
 * Default: hanya admin yang dapat masuk ke halaman admin.
 */
function periksa_sesi_admin(array $izin_peran = ['admin']) {
    if (!isset($_SESSION['id_admin'])) {
        header('Location: ' . BASE_URL . '/admin/masuk_admin.php');
        exit;
    }

    $peran_aktif = $_SESSION['peran'] ?? 'admin';
    if (!in_array($peran_aktif, $izin_peran, true)) {
        header('Location: ' . BASE_URL . '/admin/masuk_admin.php');
        exit;
    }
}

/**
 * Memeriksa apakah hanya admin yang boleh masuk.
 */
function periksa_sesi_admin_hanya_admin() {
    periksa_sesi_admin(['admin']);
}

/**
 * Memeriksa apakah sebuah tabel sudah ada di database.
 * Dipakai supaya halaman menampilkan pesan yang ramah (bukan fatal error putih)
 * kalau admin belum sempat mengimpor ulang database/rotama_jaya_online.sql versi terbaru.
 */
function tabel_ada($koneksi, $nama_tabel) {
    static $singgahan = [];
    if (isset($singgahan[$nama_tabel])) {
        return $singgahan[$nama_tabel];
    }
    try {
        $koneksi->query("SELECT 1 FROM `$nama_tabel` LIMIT 1");
        return $singgahan[$nama_tabel] = true;
    } catch (PDOException $e) {
        return $singgahan[$nama_tabel] = false;
    }
}

/**
 * Mencatat satu baris aktivitas admin ke tabel log_aktivitas (audit trail).
 * Dipanggil setelah aksi tambah/ubah/hapus BERHASIL dijalankan — bukan sebelum,
 * supaya log tidak mencatat aksi yang sebenarnya gagal.
 *
 * @param PDO    $koneksi     Koneksi database aktif.
 * @param string $modul       Modul terkait: 'pesanan' | 'desain' | 'kategori' | 'admin' | 'pengaturan' | 'auth'.
 * @param string $aksi        Jenis aksi: 'tambah' | 'ubah' | 'hapus' | 'login' | 'login_gagal'.
 * @param string $keterangan  Deskripsi singkat siap tampil ke admin, mis. 'Mengubah status pesanan #12 menjadi "selesai"'.
 *
 * Sengaja "diam-diam gagal" (try/catch tanpa melempar ulang exception): kalau
 * tabel log_aktivitas belum ada (database lama belum dimigrasi) atau terjadi
 * error tak terduga, pencatatan log TIDAK BOLEH menggagalkan aksi utama
 * admin (mis. update status pesanan tetap harus berhasil walau logging gagal).
 */
function catat_log_aktivitas(PDO $koneksi, string $modul, string $aksi, string $keterangan): void {
    if (!tabel_ada($koneksi, 'log_aktivitas')) {
        return;
    }
    try {
        $id_admin = $_SESSION['id_admin'] ?? null;
        $nama_admin = $_SESSION['nama_admin'] ?? 'Sistem';
        $koneksi->prepare(
            "INSERT INTO log_aktivitas (id_admin, nama_admin_saat_itu, modul, aksi, keterangan)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$id_admin, $nama_admin, $modul, $aksi, $keterangan]);
    } catch (Throwable $e) {
        error_log('Gagal mencatat log_aktivitas: ' . $e->getMessage());
    }
}

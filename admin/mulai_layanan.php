<?php
/**
 * MULAI_LAYANAN.PHP
 * Endpoint AJAX untuk mengecek & memulai layanan Python Flask
 * dari panel admin (ganti dari menjalankan jalankan_sistem.bat manual).
 *
 * Penggunaan:
 *   POST/GET ?aksi=cek   → memeriksa kesehatan layanan Python
 *   POST/GET ?aksi=mulai → memulai layanan Python di background
 */
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

header('Content-Type: application/json; charset=utf-8');
set_time_limit(60);

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? 'cek';

function cek_layanan_python(): bool {
    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/health');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 3000);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 6000);
    $respon = curl_exec($curl);
    $kode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return $respon !== false && $kode === 200;
}

function deteksi_python_cmd(): ?string {
    if (!function_exists('exec')) {
        return null;
    }
    $candidates = ['py -3.9', 'py -3', 'python3', 'python'];
    foreach ($candidates as $cmd) {
        $output = [];
        $return = 0;
        @exec($cmd . ' --version 2>&1', $output, $return);
        if ($return === 0 && !empty($output) && stripos(implode('', $output), 'python') !== false) {
            return $cmd;
        }
    }
    return null;
}

function mulai_layanan_python(string $project_dir): array {
    $python_dir  = $project_dir . '/python_service';
    $is_windows  = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $log_file    = $python_dir . '/layanan.log';
    $python_cmd  = deteksi_python_cmd();

    if ($python_cmd === null) {
        return ['berhasil' => false, 'metode' => 'none', 'error' => 'Python tidak ditemukan di PATH sistem.'];
    }

    if ($is_windows) {
        $batch_file = $python_dir . '/mulai_layanan.bat';

        if (!is_file($batch_file)) {
            return ['berhasil' => false, 'metode' => 'none', 'error' => 'File mulai_layanan.bat tidak ditemukan di folder python_service/.'];
        }

        // Metode 1: WScript.Shell COM (paling reliable di Windows service)
        if (class_exists('COM')) {
            try {
                $wsh = new COM('WScript.Shell');
                $wsh->Run('"' . $batch_file . '"', 0, false);
                return ['berhasil' => true, 'metode' => 'wscript', 'error' => null];
            } catch (com_exception $e) {
                // lanjut ke metode berikutnya
            }
        }

        // Metode 2: exec dengan start /B
        if (function_exists('exec')) {
            @exec('start /B "Layanan Python" /D "' . $python_dir . '" "' . $batch_file . '"', $output, $return_var);
            return ['berhasil' => true, 'metode' => 'exec_start', 'error' => null];
        }

        // Metode 3: popen (fallback)
        if (function_exists('popen') && function_exists('pclose')) {
            pclose(popen('start /B "" "' . $batch_file . '"', 'r'));
            return ['berhasil' => true, 'metode' => 'popen', 'error' => null];
        }

        return ['berhasil' => false, 'metode' => 'none', 'error' => 'Semua metode gagal. PHP tidak dapat menjalankan proses.'];
    } else {
        // --- Linux/macOS ---
        if (!function_exists('exec')) {
            return ['berhasil' => false, 'metode' => 'none', 'error' => 'PHP tidak memiliki fungsi exec.'];
        }
        $cmd = 'cd "' . $python_dir . '" && ' . $python_cmd . ' aplikasi_layanan.py > "' . $log_file . '" 2>&1 &';
        exec($cmd, $output, $return_var);
        return ['berhasil' => true, 'metode' => 'exec_amp', 'error' => null];
    }
}

// =====================================================================
// ACTION: cek status
// =====================================================================
if ($aksi === 'cek') {
    $aktif = cek_layanan_python();

    echo json_encode([
        'status'   => $aktif ? 'berjalan' : 'berhenti',
        'aktif'    => $aktif,
        'python'   => deteksi_python_cmd(),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// =====================================================================
// ACTION: mulai layanan
// =====================================================================
if ($aksi === 'mulai') {
    // Jika sudah berjalan, jangan mulai lagi
    if (cek_layanan_python()) {
        echo json_encode([
            'status'  => 'sudah_berjalan',
            'aktif'   => true,
            'pesan'   => 'Layanan Python sudah sedang berjalan di ' . ALAMAT_LAYANAN_PYTHON . '.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $project_dir = realpath(__DIR__ . '/..');
    if ($project_dir === false) {
        $project_dir = __DIR__ . '/..';
    }

    // Pastikan python_service ada
    if (!is_dir($project_dir . '/python_service')) {
        echo json_encode([
            'status'  => 'gagal',
            'aktif'   => false,
            'pesan'   => 'Folder python_service/ tidak ditemukan.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Mulai layanan
    $hasil = mulai_layanan_python($project_dir);

    if (!$hasil['berhasil']) {
        echo json_encode([
            'status'  => 'gagal',
            'aktif'   => false,
            'pesan'   => $hasil['error'],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Tunggu & cek berulang (TensorFlow butuh waktu untuk dimuat)
    $sudah_aktif = false;
    for ($i = 0; $i < 5; $i++) {
        sleep(3);
        if (cek_layanan_python()) {
            $sudah_aktif = true;
            break;
        }
    }

    if ($sudah_aktif) {
        echo json_encode([
            'status'  => 'berhasil',
            'aktif'   => true,
            'pesan'   => 'Layanan Python berhasil dimulai di ' . ALAMAT_LAYANAN_PYTHON . '.',
        ], JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode([
            'status'  => 'proses',
            'aktif'   => false,
            'pesan'   => 'Perintah mulai sudah dikirim (metode: ' . $hasil['metode'] . '). Layanan Python'
                       . ' mungkin masih sedang memulai — TensorFlow butuh 30-60 detik untuk dimuat.'
                       . ' Cek status kembali dalam beberapa saat. Jika masih gagal, periksa'
                       . ' python_service/layanan.log.',
        ], JSON_UNESCAPED_SLASHES);
    }
}

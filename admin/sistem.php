<?php
require_once __DIR__ . '/../koneksi_database.php';
periksa_sesi_admin();

$halaman_admin_aktif = 'sistem';
$judul_halaman_admin = 'Status Sistem';
$subjudul_halaman_admin = 'Kelola layanan Python & cek ketersediaan komponen sistem';

function cek_layanan_python(): bool {
    $curl = curl_init(ALAMAT_LAYANAN_PYTHON . '/health');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 4000);
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

function dapatkan_versi_php(): string {
    return PHP_VERSION;
}

function dapatkan_versi_mysql(): string {
    try {
        $v = $GLOBALS['koneksi']->query('SELECT VERSION() AS v')->fetch()['v'];
        return is_string($v) ? $v : '-';
    } catch (Throwable $e) {
        return 'error: ' . $e->getMessage();
    }
}

$python_cmd = deteksi_python_cmd();
$layanan_aktif = cek_layanan_python();

$log_path = realpath(__DIR__ . '/../python_service/layanan.log');
$log_content = '';
if ($log_path !== false) {
    $lines = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $log_content = implode("\n", array_slice($lines, -80));
    }
}

$versi_php = dapatkan_versi_php();
$versi_mysql = dapatkan_versi_mysql();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Status Sistem - Admin Rotama Jaya</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url_aset('assets/css/gaya.css'), ENT_QUOTES, 'UTF-8') ?>">
<style>
  .kartu-sistem {
    background: #fff; border-radius: 14px; border: 1px solid var(--warna-batas);
    box-shadow: var(--bayangan-sedang); padding: 22px 26px; margin-bottom: 22px;
  }
  .kartu-sistem h3 { margin: 0 0 14px; font-size: 18px; display: flex; align-items: center; gap: 8px; }
  .indikator-status {
    display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px;
  }
  .indikator-status .lingkaran {
    width: 12px; height: 12px; border-radius: 50%; display: inline-block;
  }
  .indikator-status.aktif .lingkaran { background: #16a34a; box-shadow: 0 0 8px #16a34a; }
  .indikator-status.non_aktif .lingkaran { background: #dc2626; }
  .indikator-status.memuat .lingkaran { background: #f59e0b; animation: pulse 1s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
  .tombol-sistem {
    padding: 10px 24px; border-radius: 999px; border: none; font-weight: 700;
    cursor: pointer; font-size: 14px; transition: all 0.2s;
  }
  .log-box {
    background: #0f172a; color: #e2e8f0; font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12.5px; padding: 16px; border-radius: 10px; max-height: 320px;
    overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-top: 12px;
  }
  .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .info-item label { font-size: 12px; color: var(--warna-teks-sekunder); text-transform: uppercase; letter-spacing: 0.5px; }
  .info-item .nilai { font-size: 16px; font-weight: 700; color: var(--warna-teks-utama); }
  .info-item .status-ok { color: #16a34a; }
  .info-item .status-gagal { color: #dc2626; }
  @media (max-width: 768px) { .grid-info { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="tata-letak-admin">
  <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

  <div class="konten-admin">
    <?php include __DIR__ . '/../includes/topbar_admin.php'; ?>

    <div class="isi-admin">

      <?php
      $pesan_sistem = '';
      if (isset($_GET['mulai'])) {
          $pesan_sistem = 'Memulai layanan Python... halaman ini akan refresh otomatis.';
      }
      ?>
      <?php if ($pesan_sistem): ?>
      <div class="pesan-admin sukses" id="pesanSistem"><?= htmlspecialchars($pesan_sistem) ?></div>
      <?php endif; ?>

      <!-- Panel: Layanan Python -->
      <div class="kartu-sistem">
        <h3>🐍 Layanan Python Flask (Mesin Rekomendasi)</h3>
        <p style="color:var(--warna-teks-sekunder);font-size:13px;margin-top:-6px;margin-bottom:16px;">
          Layanan ini menjalankan model CNN MobileNetV2 &amp; TF-IDF untuk pencarian desain.
          Harus aktif sebelum melakukan pencarian, unggah desain, atau pengujian.
        </p>

        <div id="panelStatusLayanan" style="margin-bottom:18px;">
          <span class="indikator-status <?= $layanan_aktif ? 'aktif' : 'non_aktif' ?>">
            <span class="lingkaran"></span>
            <span id="teksStatus">
              <?= $layanan_aktif ? 'Terhubung — ' . htmlspecialchars(ALAMAT_LAYANAN_PYTHON) : 'Tidak terhubung' ?>
            </span>
          </span>
        </div>

        <?php if ($layanan_aktif): ?>
        <button class="tombol-sistem" style="background:#e5e7eb;color:#374151;" id="tombolJalankan" onclick="cekStatus()">
          🔄 Refresh Status
        </button>
        <?php else: ?>
        <button class="tombol-sistem" style="background:linear-gradient(135deg,var(--warna-utama),var(--warna-utama-tua));color:#fff;" id="tombolJalankan" onclick="jalankanSistem()">
          🚀 Jalankan Sistem
        </button>
        <?php endif; ?>
      </div>

      <!-- Panel: Informasi Sistem -->
      <div class="kartu-sistem">
        <h3>💻 Informasi Sistem</h3>
        <div class="grid-info">
          <div class="info-item">
            <label>PHP</label>
            <div class="nilai"><?= htmlspecialchars($versi_php) ?></div>
          </div>
          <div class="info-item">
            <label>Database (MySQL)</label>
            <div class="nilai"><?= htmlspecialchars($versi_mysql) ?></div>
          </div>
          <div class="info-item">
            <label>Python</label>
            <div class="nilai">
              <?php if ($python_cmd): ?>
                <span class="status-ok">✓ Tersedia (<?= htmlspecialchars($python_cmd) ?>)</span>
              <?php else: ?>
                <span class="status-gagal">✗ Tidak ditemukan</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="info-item">
            <label>Alamat Layanan Python</label>
            <div class="nilai"><?= htmlspecialchars(ALAMAT_LAYANAN_PYTHON) ?></div>
          </div>
          <div class="info-item">
            <label>Ekstensi PHP (pdo_mysql)</label>
            <div class="nilai">
              <?php echo extension_loaded('pdo_mysql') ? '<span class="status-ok">✓ Aktif</span>' : '<span class="status-gagal">✗ Non-aktif</span>'; ?>
            </div>
          </div>
          <div class="info-item">
            <label>Ekstensi PHP (curl)</label>
            <div class="nilai">
              <?php echo extension_loaded('curl') ? '<span class="status-ok">✓ Aktif</span>' : '<span class="status-gagal">✗ Non-aktif</span>'; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel: Log Layanan -->
      <div class="kartu-sistem">
        <h3>📋 Log Layanan Python (terakhir 80 baris)</h3>
        <?php if ($log_content): ?>
        <div class="log-box"><?= htmlspecialchars($log_content) ?></div>
        <?php else: ?>
        <p style="color:var(--warna-teks-sekunder);font-size:13px;">
          Belum ada log. Log akan muncul setelah layanan Python pertama kali dijalankan.
        </p>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
function updateStatusTampilan(data) {
    const panel = document.getElementById('panelStatusLayanan');
    const tombol = document.getElementById('tombolJalankan');

    if (data.aktif) {
        panel.innerHTML = '<span class="indikator-status aktif"><span class="lingkaran"></span> <span>Terhubung &mdash; ' + (data.pesan || 'Layanan Python aktif') + '</span></span>';
        tombol.style.background = '#e5e7eb';
        tombol.style.color = '#374151';
        tombol.textContent = '🔄 Refresh Status';
        tombol.disabled = false;
        tombol.onclick = function() { cekStatus(); };
    } else {
        const sedang = data.status === 'proses';
        panel.innerHTML = '<span class="indikator-status ' + (sedang ? 'memuat' : 'non_aktif') + '">' +
            '<span class="lingkaran"></span> <span>' + (sedang ? 'Memulai...' : 'Tidak terhubung') + '</span></span>';
        tombol.style.background = 'linear-gradient(135deg,var(--warna-utama),var(--warna-utama-tua))';
        tombol.style.color = '#fff';
        tombol.disabled = sedang;
        tombol.textContent = sedang ? 'Memulai...' : '🚀 Jalankan Sistem';
        if (!sedang) {
            tombol.onclick = function() { jalankanSistem(); };
        }
    }
}

function cekStatus() {
    const panel = document.getElementById('panelStatusLayanan');
    const tombol = document.getElementById('tombolJalankan');
    panel.innerHTML = '<span class="indikator-status memuat"><span class="lingkaran"></span> <span>Memeriksa status...</span></span>';
    tombol.disabled = true;

    fetch('<?= BASE_URL ?>/admin/mulai_layanan.php?aksi=cek', { method: 'GET' })
        .then(r => r.json())
        .then(data => updateStatusTampilan(data))
        .catch(() => {
            panel.innerHTML = '<span class="indikator-status non_aktif"><span class="lingkaran"></span> <span>Error memeriksa status</span></span>';
            tombol.disabled = false;
            tombol.textContent = '🚀 Jalankan Sistem';
            tombol.onclick = function() { jalankanSistem(); };
        });
}

function jalankanSistem() {
    const tombol = document.getElementById('tombolJalankan');
    const panel = document.getElementById('panelStatusLayanan');
    tombol.disabled = true;
    tombol.textContent = 'Memulai...';
    panel.innerHTML = '<span class="indikator-status memuat"><span class="lingkaran"></span> <span>Mengirim perintah...</span></span>';

    const formData = new FormData();
    formData.append('aksi', 'mulai');

    fetch('<?= BASE_URL ?>/admin/mulai_layanan.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            let pesan = document.getElementById('pesanSistem');
            if (!pesan) {
                pesan = document.createElement('div');
                pesan.id = 'pesanSistem';
                document.querySelector('.isi-admin').prepend(pesan);
            }
            pesan.className = 'pesan-admin ' + (data.aktif || data.status === 'berhasil' ? 'sukses' : 'gagal');
            pesan.textContent = data.pesan || '';

            if (data.aktif || data.status === 'berhasil') {
                setTimeout(cekStatus, 1000);
            } else if (data.status === 'proses') {
                setTimeout(cekStatus, 15000);
            } else {
                tombol.disabled = false;
                tombol.textContent = '🚀 Coba Lagi';
                tombol.onclick = function() { jalankanSistem(); };
            }
        })
        .catch(err => {
            let pesan = document.getElementById('pesanSistem');
            if (!pesan) {
                pesan = document.createElement('div');
                pesan.id = 'pesanSistem';
                document.querySelector('.isi-admin').prepend(pesan);
            }
            pesan.className = 'pesan-admin gagal';
            pesan.textContent = 'Error: ' + err.message;
            tombol.disabled = false;
            tombol.textContent = '🚀 Coba Lagi';
            tombol.onclick = function() { jalankanSistem(); };
        });
}

document.addEventListener('DOMContentLoaded', function() {
    cekStatus();
    setInterval(cekStatus, 10000);
});
</script>
</body>
</html>

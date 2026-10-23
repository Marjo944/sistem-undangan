# Panduan Menjalankan Sistem — Rotama Jaya

1. **"Jalankan Sistem" dari web admin** — layanan Python Flask kini bisa
   dimulai hanya dengan klik tombol di dashboard atau halaman Status Sistem
   (sidebar → "Lainnya" → "Status Sistem"), tanpa perlu membuka terminal
   atau jalankan `jalankan_sistem.bat` manual. Sistem akan memeriksa kesehatan,
   memulai proses di background, dan auto-refresh status.
2. **Perbaikan ekstraksi gambar** — `proses_ekstraksi_fitur.py` dan
   `rebuild_vektor.py` sekarang memakai PIL (`Image.open`) alih-alih
   `tensorflow.keras.preprocessing.image.load_img`, sehingga gambar WEBP,
   BMP, dan format lain yang didukung Pillow dapat diproses tanpa error
   "this model does not support image input".
3. **Pengujian CNN & TF-IDF dikelola sebagai mekanisme internal penelitian** —
   halaman evaluasi internal tetap tersedia di `pengujian/uji_cnn.php` dan
   `pengujian/uji_tfidf.php`, yang mengarahkan admin ke laporan internal yang
   aman. Halaman ini bukan menu utama dashboard dan tidak ditampilkan di UI
   operasional. Tiap halaman punya kartu ringkasan di atas (progres skenario
   menuju target 30, rata-rata Presisi/Ingatan/Skor-F1@10), tabel dirapikan,
   serta form untuk menjalankan skenario tunggal.
4. **Sistem menggunakan operasional admin-only.** Tidak ada akun, login, atau
   registrasi pelanggan. Pelanggan cukup menyampaikan kata kunci atau gambar
   referensi kepada admin untuk diproses melalui sistem.
5. **Log Aktivitas / Audit Trail** (`admin/log_aktivitas.php`, menu baru di
   sidebar "Lainnya") — mencatat otomatis siapa mengubah status pesanan,
   menghapus desain, mengubah kategori, menambah/menghapus akun admin, ganti
   pengaturan akun, dan login (berhasil/gagal). Kata sandi TIDAK PERNAH ikut
   dicatat di log. Butuh tabel `log_aktivitas` baru — lihat blok migrasi di
   `database/rotama_jaya_online.sql` kalau database sudah ada sebelumnya.
6. **Grafik ringkas tambahan di Dashboard** — "Pesanan per Status" (grafik
   batang) dan "Desain Terpopuler" (berdasarkan jumlah dipakai di pesanan).
7. **Backup database otomatis** — `scripts/backup_database.sh` (Linux/macOS)
   dan `scripts/backup_database.bat` (Windows). Mencadangkan database +
   folder `uploads/desain` & `uploads/produksi` (file .psd/.cdr produksi)
   ke satu arsip terkompresi, dengan retensi otomatis 14 hari. Bisa dijadwalkan
   lewat cron (Linux/macOS) atau Task Scheduler (Windows) — lihat komentar di
   dalam masing-masing skrip untuk langkah penjadwalannya.

## 🔧 Revisi Sebelumnya — Hasil Pengujian Ujung-ke-Ujung
Seluruh sistem baru saja diuji langsung (server PHP + MySQL + Flask dijalankan
sungguhan, bukan cuma dibaca kodenya): login admin,
tambah kategori, unggah desain, pemesanan, ubah status pesanan, ekspor laporan PDF,
dan ganti pengaturan akun — semuanya berjalan normal tanpa error.

Perbaikan pada revisi ini:
1. **`jalankan_sistem.sh` / `.bat` sekarang memeriksa ekstensi PHP `pdo_mysql` dan
   `curl` di awal.** Sebelumnya, kalau salah satu ekstensi ini belum aktif di
   PHP (sering terjadi di instalasi PHP minimal/manual, bukan XAMPP/Laragon
   bawaan), sistem akan gagal total dengan pesan teknis yang membingungkan
   ("could not find driver" atau "curl_init() tidak ditemukan"). Sekarang skrip
   langsung memberi tahu ekstensi mana yang belum aktif dan cara mengaktifkannya.
2. **File-file debug (`debug_db.php`, `debug_check_duplicates.php`,
   `debug_list_latest_designs.php`, `debug_eval.py`) dihapus dari paket.**
   File-file ini sisa proses development, bisa diakses publik tanpa login, dan
   menampilkan isi database (termasuk vektor fitur desain) ke siapa saja yang
   tahu URL-nya. Tidak aman untuk ikut diunggah ke server produksi/hosting.

## ✅ Status Sebelumnya: Sudah Diperbaiki & Saling Terkoneksi
Semua bagian sistem (halaman publik, admin, database, dan layanan Python) sudah
dicek ulang dan disambungkan. Perbaikan yang dilakukan pada revisi ini:

1. **`index.php` ditambahkan** — sekarang begitu server dijalankan dan browser
   membuka alamat root, sistem otomatis diarahkan ke `beranda.php`.
2. **Skrip jalan-otomatis** (`jalankan_sistem.bat` untuk Windows,
   `jalankan_sistem.sh` untuk Linux/macOS) — satu perintah untuk menyiapkan database
   (kalau belum ada), memasang dependensi Python (kalau belum ada), menyalakan layanan
   Python, server PHP, **dan langsung membuka browser ke halaman index**. Tidak ada lagi
   langkah manual sebelum menjalankan skrip ini.
3. **Folder unggahan (`uploads/desain/`, `uploads/produksi/`) dibuat**, dan
   `koneksi_database.php` sekarang otomatis membuatnya lagi kalau sampai terhapus,
   supaya fitur unggah desain di admin tidak pernah gagal hanya karena folder hilang.
4. **Logo & ikon dibuat** (`assets/img/logo_rotama_jaya.png`) — sebelumnya file ini
   dirujuk di banyak halaman tapi belum ada, sehingga logo tampil rusak. Silakan ganti
   dengan logo asli Rotama Jaya kapan saja.
5. **Operasional admin-only** — tidak ada akun, login, atau registrasi pelanggan.
   Pelanggan menyampaikan kata kunci atau gambar referensi kepada admin; admin
   memasukkan data tersebut ke modul pencarian dan mengelola pesanan.
6. **Penanganan error ke layanan Python** — kalau `aplikasi_layanan.py` belum
   dijalankan, halaman pencarian & unggah desain admin sekarang menampilkan pesan
   yang jelas ("Layanan Python tidak dapat dihubungi...") alih-alih layar putih/error.

## 1. Struktur Folder
```
rotama_jaya/
├── index.php                 (BARU — pintu masuk, redirect ke beranda.php)
├── beranda.php
├── katalog.php
├── cari_gambar.php
├── cari_teks.php
├── tentang_kami.php
├── koneksi_database.php
├── jalankan_sistem.bat       (BARU — jalankan semua + buka browser, Windows)
├── jalankan_sistem.sh        (BARU — jalankan semua + buka browser, Linux/macOS)
├── admin/
│   ├── masuk_admin.php
│   ├── dashboard.php
│   ├── sistem.php                  (BARU — kelola layanan Python dari web admin)
│   ├── mulai_layanan.php           (BARU — endpoint AJAX start/status layanan)
│   ├── kelola_desain.php
│   ├── log_aktivitas.php             (BARU — audit trail aksi admin)
│   ├── laporan_pengujian_cnn.php
│   ├── laporan_pengujian_tfidf.php
│   └── unduh_produksi.php
├── includes/navigasi.php
├── assets/
│   ├── css/gaya.css
│   └── img/                          (BARU — logo & ikon sudah dibuat)
├── database/rotama_jaya_online.sql (schema + data untuk lokal dan deployment online)
├── scripts/impor_data_pengujian.php
├── scripts/backup_database.sh        (BARU — backup otomatis, Linux/macOS)
├── scripts/backup_database.bat       (BARU — backup otomatis, Windows)
├── python_service/
│   ├── proses_ekstraksi_fitur.py
│   ├── aplikasi_layanan.py
│   ├── mulai_layanan.bat            (BARU — helper start Python dari web)
│   ├── mulai_layanan.sh             (BARU — helper Linux/macOS)
│   └── requirements.txt
├── uploads/desain/       (BARU — gambar pratinjau publik, dibuat otomatis)
└── uploads/produksi/     (BARU — file PSD/CDR, khusus admin, dibuat otomatis)
```

## 2. Cara Menjalankan (Sepenuhnya Otomatis — Cukup 1 Perintah)
Syarat: PHP, Python 3, dan MySQL/MariaDB (mis. lewat XAMPP/Laragon) sudah **terpasang**
di komputer, dan MySQL sedang **menyala**. Selain itu, **tidak ada langkah manual lain**
sebelum menjalankan skrip di bawah — database, dependensi Python, kedua server, sampai
membuka browser semuanya ditangani otomatis oleh skrip ini.

**Windows** — klik dua kali file `jalankan_sistem.bat` (atau jalankan dari terminal).
**Linux/macOS**:
```bash
./jalankan_sistem.sh
```

Skrip ini otomatis, setiap kali dijalankan:
1. **Memeriksa database** — kalau database `rotama_jaya` belum ada, dibuat otomatis dari
`database/rotama_jaya_online.sql`, yang sudah berisi schema dan data katalog. Saat proyek
dipindahkan ke laptop lain, salin folder proyek beserta `database/` dan `uploads/`, lalu
jalankan `jalankan_sistem.bat` / `.sh`.

> **Sebelum memindahkan proyek:** salin folder proyek secara utuh, terutama
> `database/` dan `uploads/`; jangan hanya menyalin file PHP.
2. **Memeriksa dependensi Python** — kalau `flask`/`tensorflow`/`scikit-learn` belum
   terpasang, otomatis `pip install -r python_service/requirements.txt`. Kalau sudah
   terpasang, langkah ini dilewati supaya start lebih cepat.
3. Menjalankan layanan Python Flask (`python_service/aplikasi_layanan.py`) di port 5000.
4. Menjalankan server PHP bawaan di `http://localhost:8000`.
5. **Membuka browser otomatis ke halaman index**, langsung mengarah ke Beranda.

Kalau host/nama/pengguna/sandi database Anda berbeda dari default (`localhost`,
`rotama_jaya`, `root`, tanpa sandi), salin `.env.example` menjadi `.env` di folder utama
proyek lalu isi nilainya (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) — skrip otomatis
membaca `.env` ini juga saat membuat database.

> **Setelah sistem berjalan**, admin dapat langsung masuk ke panel dan mengelola katalog,
> pesanan, kategori, serta data desain pada menu **Kelola Desain**. Dalam project ini,
> dataset penelitian yang dipakai untuk evaluasi adalah **30 desain valid** yang sudah
> tersedia di database, dengan kategori dan tema yang telah diisi. Semua proses lain —
> database, server, dependensi, perhitungan vektor CNN/TF-IDF saat desain diunggah —
> dijalankan sistem sendiri.

> **Mau di-hosting ke server sungguhan (VPS)?** Lihat panduan lengkap khusus
> untuk itu di `PANDUAN_HOSTING_VPS.md` — mencakup Nginx, PHP-FPM, MySQL, dan
> cara menjalankan layanan Python secara permanen dengan Gunicorn + systemd.

> **Untuk deployment online:** gunakan `database/rotama_jaya_online.sql`. File ini
> sudah menggabungkan schema terbaru dan data katalog saat ini, sehingga server baru
> cukup mengimpor satu file SQL. File ini menjadi satu-satunya SQL yang diperlukan
> untuk menjalankan dan memindahkan project.

## 3. Cara Manual (opsional — kalau ingin kontrol penuh / pakai XAMPP-Apache)
Kalau suatu saat ingin menjalankan setiap bagian secara terpisah tanpa skrip otomatis:

### 3a. Menyiapkan Database
```bash
mysql -u root -p < database/rotama_jaya_online.sql
```

### 3b. Menjalankan Layanan Python Flask
```bash
cd python_service
py -3.9 -m pip install -r requirements.txt
py -3.9 aplikasi_layanan.py
```

**Atau — mulai dari web admin (baru):**
Setelah masuk ke panel admin, klik **Status Sistem** di sidebar (atau lihat panel
"Layanan Python" di dashboard). Jika status menampilkan "Tidak terhubung", klik
tombol **"🚀 Jalankan Sistem"**. Sistem akan otomatis memulai layanan Python
Flask di background tanpa perlu buka terminal manual. Status akan auto-refresh
setiap 10–15 detik hingga layanan "Terhubung" (hijau).

> **Catatan:** TensorFlow butuh 30–60 detik untuk dimuat pada kali pertama.
> Abaikan saja sementara — jangan klik berulang kali. Lihat log di
> `python_service/layanan.log` bila ada masalah.
Layanan berjalan di `http://127.0.0.1:5000` — **harus tetap menyala** selagi sistem dipakai,
karena semua fitur pencarian (teks & gambar) dan unggah desain admin memanggil layanan ini.

### 3c. Menjalankan Backend PHP
⚠️ PENTING: seluruh link internal (CSS, logo, navigasi) di kode memakai path absolut
dari root domain (misal `/assets/css/gaya.css`, `/beranda.php`). Karena itu **isi folder
`rotama_jaya/` harus jadi document root**, bukan diletakkan sebagai subfolder.

- **XAMPP/Laragon**: salin ISI folder `rotama_jaya/` (bukan foldernya) langsung ke `htdocs/`,
  atau buat virtual host yang document root-nya menunjuk ke folder `rotama_jaya/`.
- Lalu buka `http://localhost/` di browser — `index.php` akan otomatis
  mengarahkan ke Beranda.
- **Atau** pakai server bawaan PHP tanpa Apache:
  ```bash
  php -S localhost:8000
  ```
  lalu buka `http://localhost:8000/`.
- Kalau memang harus dijalankan di subfolder (misal `htdocs/rotama_jaya/`), semua path
  absolut `/assets/...`, `/admin/...`, `/beranda.php`, dll di setiap file PHP perlu diberi
  awalan subfolder tersebut. Beri tahu saya kalau butuh dibuatkan versi ini.

## ⚠️ Catatan Keamanan Penting
Kata sandi admin disimpan dan dicocokkan sebagai teks biasa sesuai permintaan (untuk keperluan
skripsi). **Untuk penggunaan produksi nyata**, ganti ke `password_hash()` / `password_verify()` —
perubahan kecil di `admin/masuk_admin.php`, tanpa mengubah tampilan/alur.

Perbaikan lain yang sudah ditambahkan supaya lebih aman saat online (lihat `PANDUAN_HOSTING_VPS.md`
untuk detail lengkapnya):
- Set `APP_ENV=production` di `.env` saat sudah live, supaya pesan error teknis tidak tampil ke
  pengunjung (dicatat ke log server saja).
- Folder unggahan (`uploads/`) sekarang diproteksi (`.htaccess` untuk Apache, blok khusus di
  contoh konfigurasi Nginx) supaya file di dalamnya tidak bisa dieksekusi sebagai script.
- Upload desain di admin sekarang dibatasi ekstensinya (gambar: jpg/jpeg/png/webp; file produksi:
  psd/cdr/ai/pdf/zip/rar), supaya tidak bisa disusupi file berbahaya.
- Segera ganti kata sandi akun admin bawaan (`admin`/`admin123`) lewat menu Pengaturan setelah
  sistem live.

## 4. Pengujian & Evaluasi Sistem (untuk Bab IV Skripsi)
Sesuai Bab 3.9 & 3.12 skripsi, relevansi hasil pencarian ditentukan **otomatis dan objektif**
berdasarkan kesesuaian label kategori & tema — bukan penilaian manual manusia. Karena itu,
seluruh perhitungan Precision@K, Recall@K, dan F1-Score@K sekarang dijalankan **otomatis oleh
sistem sendiri**, tanpa CSV maupun hitung manual:

1. Pastikan katalog sudah berisi cukup desain (lewat Kelola Desain) supaya ada yang bisa dicari,
   dan tiap desain sudah diberi kategori & tema (dipakai sebagai target/ground-truth pengujian).
2. Pengujian internal dijalankan lewat jalur admin khusus: `pengujian/uji_cnn.php` dan `pengujian/uji_tfidf.php`, bukan dari menu dashboard.
3. Di tiap halaman internal ada tombol **"🚀 Jalankan Semua Skenario Otomatis"** — cara tercepat, memakai
   desain yang sudah ada di katalog sebagai bahan uji sekaligus target yang diharapkan. Boleh
   diklik berkali-kali (misalnya setelah menambah desain baru); skenario baru ditambahkan, bukan
   menimpa yang lama.
4. Kalau ingin menjalankan satu skenario tambahan, pakai form **"Uji Skenario Tunggal"** di
   bawahnya: unggah gambar referensi dan pilih kategori & tema yang sesuai pada halaman CNN,
   atau pilih desain katalog pada halaman TF-IDF agar kata kunci serta label ground-truth konsisten.
5. Sistem otomatis: menjalankan pencarian sungguhan lewat layanan Python (jalur yang sama
   persis dengan yang dipakai pelanggan), mencocokkan label kategori/tema tiap hasil, menghitung
   Precision@K/Recall@K/F1-Score@K, lalu menyimpan sebagai satu baris skenario baru. Tabel,
   kartu ringkasan (progres skenario & rata-rata metrik), dan grafik di halaman langsung terbarui.
6. Target umum untuk skripsi: **30 skenario per metode** (progres ini terlihat di kartu
   ringkasan bagian atas tiap halaman). Dataset evaluasi yang dipakai adalah desain yang
   sudah mempunyai kategori dan tema valid, sedangkan item query dikeluarkan dari hasil
   pencarian sebelum perhitungan metrik. Relevansi ditentukan berdasarkan kesamaan kategori
   dan tema.

**Cara alternatif (CSV):** kalau sudah punya angka pengujian dari sumber lain, form **"Impor
Hasil Pengujian (CSV)"** di bawahnya masih tersedia — siapkan sendiri file CSV dengan kolom
`nomor_skenario, nama_gambar_uji/kata_kunci_uji, presisi_5, presisi_10, ingatan_5, ingatan_10,
skor_f1_5, skor_f1_10`, lalu unggah. Boleh diunggah ulang kapan saja; baris dengan nomor skenario
yang sama otomatis diganti (bukan dobel). `scripts/impor_data_pengujian.php` (CLI) juga masih
tersedia sebagai alternatif lewat terminal.

## 4b. Dataset Penelitian dan Pengelolaan Desain
Dataset penelitian yang dipakai dalam sistem ini adalah **30 desain undangan valid**
(hasil subset dari katalog yang sudah ada di database) dengan kategori dan tema yang
terisi lengkap. Dataset ini tidak dibuat sebagai data dummy dan tidak ditambah secara
acak, karena sistem dirancang untuk mengevaluasi rekomendasi berdasarkan desain nyata
yang sudah tersedia di katalog.

Setelah menjalankan `jalankan_sistem.sh` / `jalankan_sistem.bat`, admin dapat masuk ke
`admin/masuk_admin.php` lalu membuka **Kelola Desain** untuk mengelola katalog, kategori,
judul, kata kunci, file gambar, dan file produksi PSD/CDR. Semua file tersebut akan
secara otomatis diproses oleh layanan Python untuk menghitung vektor CNN/TF-IDF sesuai
alur yang sudah ada.

## 5. Alur Penggunaan Singkat
1. Admin masuk di `/admin/masuk_admin.php` (akun default `admin`/`admin123`).
2. Pelanggan menyampaikan kata kunci atau gambar referensi kepada admin.
3. Admin memasukkan kata kunci atau mengunggah gambar referensi melalui modul pencarian.
4. Admin melihat rekomendasi, memilih desain, dan membuat pesanan.
5. Admin mengelola desain, pesanan, dan evaluasi internal dari panel admin yang relevan.

## 🔜 Masih Bisa Dilengkapi (opsional, beri tahu saya kalau perlu)
1. Logo asli Rotama Jaya (saat ini memakai logo placeholder otomatis)
2. Ganti pencocokan kata sandi teks biasa → hash, kalau untuk produksi nyata
3. Penyesuaian detail tata letak agar 100% cocok posisi elemen di Bab IV skripsi Anda
   (perlu saya lihat gambar/deskripsi rancangan antarmuka Bab IV untuk mencocokkan persis)

http://localhost/rotama_jaya1_fixed2/pengujian/uji_cnn.php
http://localhost/rotama_jaya1_fixed2/pengujian/uji_tfidf.php

# Panduan Hosting ke VPS — Sistem Rotama Jaya

Panduan ini untuk memindahkan sistem dari komputer lokal (XAMPP/Laragon) ke
**VPS/cloud server sungguhan** (Ubuntu 22.04/24.04 dipakai sebagai contoh —
langkahnya serupa untuk distro Linux lain, cuma nama perintah paket bisa beda).

Kenapa harus VPS (bukan shared hosting cPanel biasa)? Karena sistem ini
punya **layanan Python (Flask)** yang harus terus menyala di background untuk
mesin rekomendasi (CNN & TF-IDF) — kebanyakan shared hosting murah cuma
menyediakan PHP+MySQL dan tidak mengizinkan proses Python berjalan terus-menerus.

---

## 0. Gambaran Arsitektur di Server

```
Pengunjung (browser)
        │  HTTPS (443)
        ▼
     Nginx  ──── melayani file statis (CSS/gambar) langsung
        │  ──── meneruskan *.php ke PHP-FPM
        ▼
    PHP-FPM  (menjalankan seluruh halaman .php)
        │
        ├──► MySQL (localhost, port 3306)
        │
        └──► curl ke http://127.0.0.1:5000 (layanan Python)
                        ▲
                        │  dijalankan permanen oleh systemd + Gunicorn
                   Layanan Python (Flask + Gunicorn)
```

Python **tidak** diakses langsung oleh pengunjung dari luar — hanya dipanggil
dari PHP di server yang sama (`127.0.0.1:5000`), jadi tidak perlu domain/SSL
terpisah untuknya.

---

## 1. Persiapan Server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php-fpm php-mysql php-curl \
    python3 python3-venv python3-pip git unzip certbot python3-certbot-nginx
```

Amankan instalasi MySQL:
```bash
sudo mysql_secure_installation
```

## 2. Unggah Berkas Proyek

Unggah folder `rotama_jaya/` ke server, misalnya ke `/var/www/rotama_jaya`
(lewat `scp`, `rsync`, atau `git clone` kalau proyeknya ada di repository):

```bash
sudo mkdir -p /var/www/rotama_jaya
# contoh dari komputer lokal:
# scp -r rotama_jaya/* user@ip-server:/var/www/rotama_jaya/
sudo chown -R www-data:www-data /var/www/rotama_jaya
```

## 3. Konfigurasi `.env`

```bash
cd /var/www/rotama_jaya
cp .env.example .env
nano .env
```

Isi minimal untuk produksi:
```
APP_ENV=production
DB_HOST=localhost
DB_NAME=rotama_jaya
DB_USER=rotama_user
DB_PASSWORD=isi_dengan_sandi_kuat
ALAMAT_LAYANAN_PYTHON=http://127.0.0.1:5000
# PHP-FPM memakai binary ini untuk menjalankan job pemrosesan desain di background.
PHP_CLI_BIN=/usr/bin/php
```

> `APP_ENV=production` membuat pesan error teknis **tidak** ditampilkan ke
> pengunjung (dicatat ke log server saja) — penting supaya detail internal
> sistem tidak bocor ke publik.

## 3.5. WAJIB: Naikkan Batas Ukuran Unggahan (file produksi PSD/CDR)

**Jangan lewati langkah ini.** Bawaan PHP hanya mengizinkan unggahan sekitar
2–8 MB, sedangkan file produksi (PSD/CDR) percetakan biasanya berukuran
puluhan hingga ratusan MB. Tanpa langkah ini, admin akan gagal mengunggah
file produksi begitu ukurannya melebihi batas bawaan (sistem sudah menampilkan
pesan yang jelas soal ini, tapi tetap perlu dinaikkan supaya unggahan berhasil).

Edit `php.ini` PHP-FPM yang dipakai (cari lokasinya dengan `php --ini`,
biasanya `/etc/php/8.3/fpm/php.ini`):
```ini
upload_max_filesize = 300M
post_max_size = 320M
max_execution_time = 300
memory_limit = 512M
```

Naikkan juga batas di Nginx (`client_max_body_size`, default cuma 1 MB) —
lihat konfigurasi `server { ... }` di langkah 6, tambahkan baris ini di
dalamnya:
```nginx
client_max_body_size 320M;
```

Restart PHP-FPM dan Nginx setelah mengubah kedua konfigurasi ini:
```bash
sudo systemctl restart php8.3-fpm nginx   # sesuaikan versi PHP
```

Sesuaikan angka `300M`/`320M` dengan ukuran file produksi terbesar yang
biasa dipakai di percetakan Anda — beri sedikit ruang lebih (contoh di atas:
batas unggah 300MB, batas total request 320MB).

## 4. Basis Data

Buat database & user MySQL khusus (jangan pakai `root` di produksi):
```bash
sudo mysql -e "
CREATE DATABASE rotama_jaya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rotama_user'@'localhost' IDENTIFIED BY 'isi_dengan_sandi_kuat';
GRANT ALL PRIVILEGES ON rotama_jaya.* TO 'rotama_user'@'localhost';
FLUSH PRIVILEGES;
"
```

Impor database deployment (satu file ini sudah berisi struktur terbaru dan data katalog):
```bash
mysql -u rotama_user -p rotama_jaya < database/rotama_jaya_online.sql
```

Untuk instalasi lokal maupun server online gunakan satu file:
`database/rotama_jaya_online.sql`.

### 4b. Indeks untuk katalog, pesanan, dan riwayat pencarian

Skema baru sudah menyertakan indeks ini. Jika database sudah lama dibuat sebelum optimasi, jalankan
blok berikut **satu kali** sebagai user database (lewati indeks yang sudah ada):

```sql
ALTER TABLE desain_undangan
  ADD INDEX idx_desain_dibuat_pada (dibuat_pada),
  ADD INDEX idx_desain_kategori_dibuat_pada (id_kategori, dibuat_pada);

ALTER TABLE riwayat_pencarian
  ADD INDEX idx_riwayat_jenis_waktu (jenis_pencarian, dicari_pada);

ALTER TABLE pesanan
  ADD INDEX idx_pesanan_status (status),
  ADD INDEX idx_pesanan_dibuat_pada (dibuat_pada);
```

Indeks ini membantu halaman katalog, dashboard, serta daftar pesanan tetap responsif ketika
jumlah data bertambah. Tidak perlu menambahkan indeks pada kolom vektor karena kolom tersebut
hanya dipakai untuk perhitungan layanan Python.

## 5. Layanan Python — Jalankan Permanen dengan Gunicorn + systemd

Gunakan Python **3.9 sampai 3.12**. Versi Python 3.13 belum kompatibel dengan
`tensorflow==2.16.1` pada `requirements.txt`.

```bash
python3 --version
cd /var/www/rotama_jaya/python_service
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
deactivate
```

Buat file service systemd `/etc/systemd/system/rotama-python.service`:
```ini
[Unit]
Description=Layanan Python (mesin rekomendasi) Rotama Jaya
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/rotama_jaya/python_service
EnvironmentFile=/var/www/rotama_jaya/.env
ExecStart=/var/www/rotama_jaya/python_service/venv/bin/gunicorn \
    --workers 2 --timeout 120 --bind 127.0.0.1:5000 aplikasi_layanan:aplikasi
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Aktifkan:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now rotama-python
sudo systemctl status rotama-python
```

> Catatan: `--workers 2` cukup untuk skala kecil/menengah. TensorFlow cukup
> berat di memori, jadi pastikan VPS punya RAM minimal ~2 GB. Kalau sering
> kehabisan memori, turunkan jadi `--workers 1`.

Cek log kalau ada masalah:
```bash
sudo journalctl -u rotama-python -f
```

## 6. Konfigurasi Nginx + PHP-FPM

Buat `/etc/nginx/sites-available/rotama_jaya`:
```nginx
server {
    listen 80;
    server_name domainanda.com www.domainanda.com;
    root /var/www/rotama_jaya;
    index index.php;

    # Kompresi respons teks agar CSS/JS/HTML/JSON lebih ringan saat dikirim.
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/html application/javascript application/json application/xml image/svg+xml;

    # Cache browser untuk aset statis; halaman PHP tetap diproses dinamis.
    location ~* \.(?:css|js|jpg|jpeg|png|webp|gif|svg|ico)$ {
        expires 7d;
        add_header Cache-Control "public, max-age=604800";
        access_log off;
    }

    # Batas ukuran unggahan (lihat Langkah 3.5) — sesuaikan dengan ukuran
    # file produksi PSD/CDR terbesar yang biasa dipakai.
    client_max_body_size 320M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock; # sesuaikan versi PHP, mis. php8.3-fpm.sock
    }

    # Folder unggahan: gambar boleh diakses publik, tapi TIDAK BOLEH
    # menjalankan script apa pun, walau ada file .php tersasar di sana.
    location /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }

    location ~ /\.(env|git) {
        deny all;
    }
}
```

Aktifkan dan cek konfigurasi:
```bash
sudo ln -s /etc/nginx/sites-available/rotama_jaya /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Pasang SSL gratis (Let's Encrypt):
```bash
sudo certbot --nginx -d domainanda.com -d www.domainanda.com
```

## 7. Izin Folder Unggahan

```bash
sudo chown -R www-data:www-data /var/www/rotama_jaya/uploads
sudo chmod -R 775 /var/www/rotama_jaya/uploads
# Layanan Python harus dapat membuat file sementara dan memperbarui model TF-IDF.
sudo chown www-data:www-data /var/www/rotama_jaya/python_service
sudo chmod 775 /var/www/rotama_jaya/python_service
if [ -f /var/www/rotama_jaya/python_service/model_tfidf.pkl ]; then
  sudo chown www-data:www-data /var/www/rotama_jaya/python_service/model_tfidf.pkl
  sudo chmod 664 /var/www/rotama_jaya/python_service/model_tfidf.pkl
fi
# PHP-FPM juga harus dapat menjalankan PHP CLI untuk job pemrosesan background.
command -v /usr/bin/php
php -r 'echo PHP_SAPI, PHP_EOL;'
```

## 8. Uji Coba Akhir

- Buka `https://domainanda.com/` → harus redirect ke Beranda.
- Coba fitur pencarian gambar & kata kunci → pastikan layanan Python merespons
  (`sudo systemctl status rotama-python` harus `active (running)`).
- Login admin (`/admin/masuk_admin.php`, akun awal `admin`/`admin123` — **segera
  ganti** lewat menu Pengaturan setelah login pertama) dan coba unggah desain baru.
- Setelah mengunggah, status desain harus berubah dari `Sedang diproses` menjadi
  `Siap dipakai`. Jika tetap `Sedang diproses`, cek `sudo -u www-data /usr/bin/php
  -v`, `sudo systemctl status rotama-python`, dan log PHP-FPM. Pastikan `exec` dan
  `popen` tidak dinonaktifkan di `disable_functions` PHP.

## 9. Setelah Live — Hal yang Perlu Dipantau

- **Update aplikasi**: setelah menimpa file PHP/Python di server, jalankan
  `sudo systemctl restart rotama-python` kalau ada perubahan di folder
  `python_service/`. Perubahan file PHP langsung berlaku tanpa restart apa pun.
- **Backup database + file desain/produksi + model TF-IDF otomatis**: pakai `scripts/backup_database.sh`
  (sudah tersedia di paket ini). Jadwalkan lewat cron supaya jalan sendiri tiap
  hari tanpa perlu diingat-ingat manual:
  ```bash
  chmod +x scripts/backup_database.sh
  crontab -e
  # tambahkan baris ini (jam 02:00 setiap hari):
  0 2 * * * /var/www/rotama_jaya/scripts/backup_database.sh >> /var/log/rotama_jaya_backup.log 2>&1
  ```
  Cadangan tersimpan di folder `backups/` (otomatis dibuat, dan cadangan lebih
  tua dari 14 hari otomatis dihapus supaya disk tidak penuh). **Salin folder
  ini secara berkala ke penyimpanan terpisah** (Google Drive, S3, dll) — cadangan
  yang hanya ada di server yang sama tidak banyak membantu kalau server itu sendiri
  yang bermasalah/hilang.
- **Cadangkan file `.env`** di tempat aman terpisah — kalau server hilang,
  ini satu-satunya salinan kata sandi database Anda.

### Pemulihan setelah server baru

Ekstrak arsip backup ke folder proyek, lalu impor SQL dan pulihkan service:
```bash
cd /var/www/rotama_jaya
tar -xzf /lokasi/rotama_jaya_backup_TANGGAL.tar.gz
mysql -u rotama_user -p rotama_jaya < database_rotama_jaya.sql
sudo chown -R www-data:www-data uploads python_service/model_tfidf.pkl
sudo systemctl restart rotama-python php8.3-fpm nginx
```

Arsip backup berisi tiga sumber data penting: database (metadata, pesanan, dan
vektor CNN/TF-IDF), `uploads/` (gambar desain dan file produksi), serta
`python_service/model_tfidf.pkl` (vectorizer TF-IDF). Jangan hanya mengunggah
file PHP ke server baru, karena itu tidak membawa data katalog.

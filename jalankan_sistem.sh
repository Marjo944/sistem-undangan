#!/bin/bash
# =====================================================
# JALANKAN_SISTEM.SH
# Menjalankan SELURUH sistem Rotama Jaya secara otomatis (Linux/macOS),
# tanpa perlu langkah manual apa pun sebelumnya. Skrip ini otomatis:
#   1) Membuat database & tabel + memulihkan data dari database/rotama_jaya_online.sql
#   2) Memasang dependensi Python (kalau belum terpasang)
#   3) Menjalankan layanan Python Flask (mesin rekomendasi)
#   4) Menjalankan server PHP bawaan (php -S)
#   5) Membuka browser otomatis ke halaman index/beranda
#
# SYARAT: PHP, Python 3, dan MySQL/MariaDB server sudah terpasang &
#         MySQL sedang menyala. Selebihnya ditangani otomatis oleh skrip ini.
#
# Satu-satunya langkah manual yang tersisa SETELAH sistem berjalan adalah
# menginput data desain lewat panel admin (Kelola Desain) — bukan bagian
# dari skrip ini karena memang harus diisi satu per satu sesuai desain nyata.
# =====================================================
set -e
cd "$(dirname "$0")"

PORT=8000
URL="http://localhost:$PORT/index.php"

# ---- [0/4] Memuat konfigurasi dari .env (kalau ada), dengan nilai bawaan ----
DB_HOST="localhost"
DB_NAME="rotama_jaya"
DB_USER="root"
DB_PASSWORD=""
if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

MYSQL_AUTH=(-h "$DB_HOST" -u "$DB_USER")
if [ -n "$DB_PASSWORD" ]; then
    MYSQL_AUTH+=(-p"$DB_PASSWORD")
fi

# ---- [0.5/4] Memeriksa ekstensi PHP wajib (pdo_mysql, curl) ----
echo "[0.5/4] Memeriksa ekstensi PHP ..."
EKSTENSI_HILANG=()
php -m 2>/dev/null | grep -qi '^pdo_mysql$' || EKSTENSI_HILANG+=("pdo_mysql")
php -m 2>/dev/null | grep -qi '^curl$' || EKSTENSI_HILANG+=("curl")
if [ ${#EKSTENSI_HILANG[@]} -gt 0 ]; then
    echo "    ✘ Ekstensi PHP berikut belum aktif: ${EKSTENSI_HILANG[*]}"
    echo "      Tanpa ini, halaman akan gagal total ('could not find driver') atau"
    echo "      unggah desain di admin akan error ('curl_init() tidak ditemukan')."
    echo "      - XAMPP/Laragon: buka php.ini, hapus tanda ';' di depan"
    echo "        extension=pdo_mysql dan extension=curl, lalu restart Apache/PHP."
    echo "      - Ubuntu/Debian: sudo apt install php-mysql php-curl"
    echo "      Skrip tetap dilanjutkan, tapi sistem kemungkinan tidak akan berjalan normal."
else
    echo "    ✔ Ekstensi pdo_mysql & curl sudah aktif."
fi

# ---- [1/4] Menyiapkan database secara otomatis (kalau belum ada) ----
echo "[1/4] Memeriksa database ..."
if ! command -v mysql > /dev/null 2>&1; then
    echo "    ⚠ Perintah 'mysql' tidak ditemukan di PATH. Lewati pemeriksaan otomatis;"
    echo "      pastikan database '$DB_NAME' sudah dibuat manual dari database/rotama_jaya_online.sql."
else
    DB_SUDAH_ADA=$(mysql "${MYSQL_AUTH[@]}" -N -e "SHOW DATABASES LIKE '$DB_NAME';" 2>/dev/null || true)
    if [ -z "$DB_SUDAH_ADA" ]; then
        echo "    Database '$DB_NAME' belum ada. Membuat dari database/rotama_jaya_online.sql ..."
        mysql "${MYSQL_AUTH[@]}" < database/rotama_jaya_online.sql
        echo "    ✔ Database dibuat dari schema dan data deployment online."
    else
        echo "    ✔ Database '$DB_NAME' sudah ada, lewati pembuatan ulang."
    fi

fi

# ---- [2/4] Memasang dependensi Python secara otomatis (kalau belum) ----
echo "[2/4] Memeriksa dependensi Python ..."
PY_BIN="python3"
command -v python3 > /dev/null 2>&1 || PY_BIN="python"
PY_MAJOR_MINOR=$("$PY_BIN" -c "import sys; print(f'{sys.version_info.major}.{sys.version_info.minor}')" 2>/dev/null || true)
case "$PY_MAJOR_MINOR" in
    3.9|3.10|3.11|3.12) ;;
    *)
        echo "    ✘ Python $PY_MAJOR_MINOR tidak didukung oleh TensorFlow 2.16.1."
        echo "      Gunakan Python 3.9 sampai 3.12, lalu jalankan ulang skrip."
        exit 1
        ;;
esac
if ! "$PY_BIN" -c "import flask, sklearn, tensorflow" > /dev/null 2>&1; then
    echo "    Memasang dependensi Python dari python_service/requirements.txt ..."
    (cd python_service && "$PY_BIN" -m pip install -q -r requirements.txt)
    echo "    ✔ Dependensi Python terpasang."
else
    echo "    ✔ Dependensi Python sudah lengkap, lewati instalasi."
fi

# ---- [3/4] Menjalankan layanan Python (Flask) & server PHP ----
echo "[3/4] Menjalankan layanan Python (Flask) di port 5000 ..."
(cd python_service && "$PY_BIN" aplikasi_layanan.py) &
PID_PYTHON=$!

sleep 3

echo "    Menjalankan server PHP di $URL ..."
php -S localhost:$PORT &
PID_PHP=$!

sleep 2

# ---- [4/4] Membuka browser ----
echo "[4/4] Membuka browser di halaman index ..."
if command -v xdg-open > /dev/null 2>&1; then
    xdg-open "$URL" > /dev/null 2>&1
elif command -v open > /dev/null 2>&1; then
    open "$URL"
else
    echo "    Tidak bisa membuka browser otomatis. Buka manual: $URL"
fi

echo ""
echo "Sistem berjalan sepenuhnya otomatis (Layanan Python PID $PID_PYTHON, Server PHP PID $PID_PHP)."
echo "Satu-satunya langkah manual yang tersisa: login ke /admin dan input data desain lewat 'Kelola Desain'."
echo "Tekan CTRL+C untuk menghentikan kedua layanan."

trap "kill $PID_PYTHON $PID_PHP 2>/dev/null" EXIT
wait

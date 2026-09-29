#!/bin/bash
# =====================================================
# BACKUP_DATABASE.SH
# Mencadangkan database MySQL/MariaDB (mysqldump) dan folder unggahan
# (uploads/desain, uploads/produksi — file .psd/.cdr produksi yang PENTING
# bagi bisnis dan TIDAK ADA salinannya di tempat lain) ke satu file .tar.gz
# per hari, lalu menghapus otomatis cadangan yang lebih tua dari
# RETENSI_HARI hari supaya disk tidak penuh.
#
# CARA PAKAI MANUAL:
#   ./scripts/backup_database.sh
#
# CARA MENJADWALKAN OTOMATIS (Linux/macOS, tiap hari jam 02:00):
#   1. Jalankan:  crontab -e
#   2. Tambahkan baris (ganti path sesuai lokasi instalasi):
#      0 2 * * * /var/www/rotama_jaya/scripts/backup_database.sh >> /var/log/rotama_jaya_backup.log 2>&1
#
# Skrip ini membaca konfigurasi dari .env (sama seperti aplikasi utama),
# jadi tidak perlu isi kredensial database dua kali.
# =====================================================
set -e
cd "$(dirname "$0")/.."

# ---- Muat konfigurasi dari .env (kalau ada) ----
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

RETENSI_HARI=14                     # Cadangan lebih tua dari ini otomatis dihapus
DIREKTORI_BACKUP="backups"
TANGGAL="$(date +%Y-%m-%d_%H-%M-%S)"
FILE_BACKUP="$DIREKTORI_BACKUP/rotama_jaya_backup_$TANGGAL.tar.gz"

mkdir -p "$DIREKTORI_BACKUP"

MYSQL_AUTH=(-h "$DB_HOST" -u "$DB_USER")
if [ -n "$DB_PASSWORD" ]; then
    MYSQL_AUTH+=(-p"$DB_PASSWORD")
fi

echo "[1/3] Mencadangkan database '$DB_NAME'..."
STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT
mysqldump "${MYSQL_AUTH[@]}" --single-transaction --routines --triggers "$DB_NAME" > "$STAGING/database_${DB_NAME}.sql"

echo "[2/3] Mengemas database + unggahan + model TF-IDF menjadi satu arsip..."
mkdir -p "$STAGING/uploads"
[ -d uploads/desain ]   && cp -r uploads/desain   "$STAGING/uploads/"
[ -d uploads/produksi ] && cp -r uploads/produksi "$STAGING/uploads/"
[ -f python_service/model_tfidf.pkl ] && mkdir -p "$STAGING/python_service" \
    && cp python_service/model_tfidf.pkl "$STAGING/python_service/"
tar -czf "$FILE_BACKUP" -C "$STAGING" .

echo "    -> Tersimpan: $FILE_BACKUP ($(du -h "$FILE_BACKUP" | cut -f1))"

echo "[3/3] Menghapus cadangan lebih tua dari $RETENSI_HARI hari..."
find "$DIREKTORI_BACKUP" -name 'rotama_jaya_backup_*.tar.gz' -mtime +"$RETENSI_HARI" -print -delete

echo "Selesai. Total cadangan tersimpan saat ini: $(find "$DIREKTORI_BACKUP" -name 'rotama_jaya_backup_*.tar.gz' | wc -l | tr -d ' ')"
echo ""
echo "⚠️  PENTING: file di folder '$DIREKTORI_BACKUP' ini masih di server yang sama."
echo "    Salin secara berkala ke tempat TERPISAH (Google Drive, S3, hard disk eksternal, dll)"
echo "    supaya tetap aman kalau server/VPS bermasalah."

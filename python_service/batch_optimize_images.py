"""
BATCH_OPTIMIZE_IMAGES.PY
Skrip batch untuk mengompres gambar yang sudah ada di folder uploads/desain/
dan memperbarui kolom nama_file_webp di database.

Cara pakai:
  python batch_optimize_images.py

Skrip ini akan:
  1. Memindai semua file gambar di uploads/desain/
  2. Mengompres setiap gambar menjadi WebP (maks 1200px, quality 82)
  3. Menyimpan versi WebP dengan prefix 'webp_'
  4. Memperbarui kolom nama_file_webp di tabel desain_undangan
"""

from __future__ import annotations

import os
import sys
import json
import hashlib
import uuid
from pathlib import Path

try:
    from PIL import Image
except ImportError:
    print("Error: Pillow tidak terinstal. Jalankan: pip install Pillow")
    sys.exit(1)

# Konfigurasi
DIREKTORI_PROYEK = Path(__file__).resolve().parent.parent
DIREKTORI_GAMBAR = DIREKTORI_PROYEK / 'uploads' / 'desain'
MAKS_UKURAN = 1200
KUALITAS_WEBP = 82

# Konfigurasi database
KONFIGURASI_DB = {
    'host': os.environ.get('DB_HOST', 'localhost'),
    'user': os.environ.get('DB_USER', 'root'),
    'password': os.environ.get('DB_PASSWORD', ''),
    'database': os.environ.get('DB_NAME', 'rotama_jaya'),
}

def kompres_gambar(path_sumber: Path) -> tuple[str, str] | None:
    """
    Kompres gambar dan kembalikan (nama_file_webp, path_webp).
    """
    try:
        nama_webp = f"webp_{uuid.uuid4().hex}.webp"
        path_webp = DIREKTORI_GAMBAR / nama_webp
        
        with Image.open(path_sumber) as img:
            if img.mode in ('RGBA', 'P'):
                img = img.convert('RGB')
            lebar, tinggi = img.size
            if max(lebar, tinggi) > MAKS_UKURAN:
                skala = MAKS_UKURAN / max(lebar, tinggi)
                img = img.resize(
                    (int(lebar * skala), int(tinggi * skala)),
                    Image.Resampling.LANCZOS
                )
            img.save(path_webp, 'WEBP', quality=KUALITAS_WEBP, method=6)
        
        ukuran_asli = path_sumber.stat().st_size / 1024
        ukuran_webp = path_webp.stat().st_size / 1024
        print(f"  ✓ {path_sumber.name} -> {nama_webp} ({ukuran_asli:.0f}KB -> {ukuran_webp:.0f}KB)")
        return nama_webp, str(path_webp)
    except Exception as e:
        print(f"  ✗ Gagal memproses {path_sumber.name}: {e}")
        return None


def muat_koneksi():
    try:
        import mysql.connector
        return mysql.connector.connect(**KONFIGURASI_DB)
    except ImportError:
        print("Error: mysql-connector-python tidak terinstal. Jalankan: pip install mysql-connector-python")
        sys.exit(1)


def main():
    print("=== Batch Optimasi Gambar ===")
    print(f"Direktori: {DIREKTORI_GAMBAR}")
    
    if not DIREKTORI_GAMBAR.exists():
        print(f"Error: Direktori {DIREKTORI_GAMBAR} tidak ditemukan")
        sys.exit(1)
    
    # Kumpulkan semua file gambar
    ekstensi_diizinkan = {'.jpg', '.jpeg', '.png', '.webp'}
    file_gambar = []
    for f in DIREKTORI_GAMBAR.iterdir():
        if f.is_file() and f.suffix.lower() in ekstensi_diizinkan and not f.name.startswith('webp_'):
            file_gambar.append(f)
    
    print(f"Ditemukan {len(file_gambar)} file gambar untuk dioptimasi")
    
    if not file_gambar:
        print("Tidak ada file yang perlu dioptimasi")
        return
    
    # Koneksi database
    koneksi = muat_koneksi()
    kursor = koneksi.cursor(dictionary=True)
    
    # Ambil semua desain dari database
    kursor.execute("SELECT id_desain, nama_file_gambar, nama_file_webp FROM desain_undangan")
    desain_db = {row['nama_file_gambar']: row for row in kursor.fetchall()}
    
    print(f"Ditemukan {len(desain_db)} desain di database")
    
    diperbarui = 0
    dilewati = 0
    gagal = 0
    
    for path_gambar in file_gambar:
        nama_file = path_gambar.name
        
        # Cek apakah sudah ada di database
        if nama_file not in desain_db:
            print(f"  - {nama_file} (tidak ada di database, dilewati)")
            dilewati += 1
            continue
        
        desain = desain_db[nama_file]
        
        # Cek apakah sudah ada versi WebP
        if desain['nama_file_webp'] and (DIREKTORI_GAMBAR / desain['nama_file_webp']).exists():
            print(f"  = {nama_file} (sudah ada WebP: {desain['nama_file_webp']})")
            dilewati += 1
            continue
        
        # Kompres gambar
        hasil = kompres_gambar(path_gambar)
        if hasil is None:
            gagal += 1
            continue
        
        nama_webp, path_webp = hasil
        
        # Update database
        try:
            kursor.execute(
                "UPDATE desain_undangan SET nama_file_webp = %s WHERE id_desain = %s",
                (nama_webp, desain['id_desain'])
            )
            koneksi.commit()
            diperbarui += 1
        except Exception as e:
            print(f"  ✗ Gagal update database untuk {nama_file}: {e}")
            gagal += 1
            if path_webp.exists():
                path_webp.unlink()
    
    kursor.close()
    koneksi.close()
    
    print(f"\n=== Selesai ===")
    print(f"Diperbarui : {diperbarui}")
    print(f"Dilewati   : {dilewati}")
    print(f"Gagal      : {gagal}")


if __name__ == '__main__':
    main()

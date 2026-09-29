@echo off
REM =====================================================
REM MULAI_LAYANAN.BAT - Helper untuk memulai layanan Python
REM Dipanggil oleh admin/mulai_layanan.php saat admin klik
REM tombol "Jalankan Sistem" di panel admin web.
REM
REM Batch file ini HANYA menjalankan aplikasi_layanan.py saja.
REM Database dan dependensi Python tetap di-setup oleh jalankan_sistem.bat.
REM =====================================================
cd /d "%~dp0"

REM Coba py -3.9 dulu, lalu python
py -3.9 aplikasi_layanan.py > layanan.log 2>&1
if %errorlevel% equ 9009 (
    REM Command not found, coba python
    python aplikasi_layanan.py > layanan.log 2>&1
)

@echo off
REM =====================================================
REM JALANKAN_SISTEM.BAT
REM Menjalankan SELURUH sistem Rotama Jaya secara otomatis (Windows),
REM tanpa perlu langkah manual apa pun sebelumnya. Skrip ini otomatis:
REM   1) Membuat database & tabel + memulihkan data dari database\rotama_jaya_online.sql
REM   2) Memasang dependensi Python (kalau belum terpasang)
REM   3) Menjalankan layanan Python Flask (mesin rekomendasi)
REM   4) Menjalankan server PHP bawaan (php -S)
REM   5) Membuka browser otomatis ke halaman index/beranda
REM
REM SYARAT: PHP, Python, dan MySQL/MariaDB server (mis. XAMPP/Laragon) sudah
REM         terpasang & MySQL sedang menyala. Selebihnya otomatis.
REM
REM Satu-satunya langkah manual yang tersisa SETELAH sistem berjalan adalah
REM menginput data desain lewat panel admin (Kelola Desain).
REM =====================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"

REM Gunakan PHP XAMPP bila php.exe belum masuk ke PATH Windows.
set "PHP_CMD=php"
if exist "C:\xampp\php\php.exe" set "PHP_CMD=C:\xampp\php\php.exe"
%PHP_CMD% -v >nul 2>nul
if errorlevel 1 (
    echo     PERINGATAN: PHP tidak ditemukan. Pastikan PHP XAMPP terpasang.
    exit /b 1
)

REM Gunakan Python 3.9 yang dipakai layanan ini. Alias 'python' Windows
REM kadang menunjuk ke instalasi Microsoft Store yang sudah tidak tersedia.
set "PYTHON_CMD=py -3.9"
%PYTHON_CMD% --version >nul 2>nul
if errorlevel 1 (
    echo     PERINGATAN: Python 3.9 tidak ditemukan. Pasang Python 3.9 atau ubah PYTHON_CMD di skrip ini.
    exit /b 1
)

REM XAMPP sering tidak menambahkan MySQL ke PATH Windows. Pakai lokasi bawaan
REM bila tersedia, supaya ekspor/impor data portabel tetap berjalan otomatis.
if exist "C:\xampp\mysql\bin\mysql.exe" set "PATH=C:\xampp\mysql\bin;%PATH%"

REM ---- [0/4] Memuat konfigurasi dari .env (kalau ada), dengan nilai bawaan ----
set "DB_HOST=localhost"
set "DB_NAME=rotama_jaya"
set "DB_USER=root"
set "DB_PASSWORD="

if exist ".env" (
    for /f "usebackq tokens=1,* delims==" %%A in (".env") do (
        set "line=%%A"
        if not "!line:~0,1!"=="#" if not "%%A"=="" (
            if "%%A"=="DB_HOST" set "DB_HOST=%%B"
            if "%%A"=="DB_NAME" set "DB_NAME=%%B"
            if "%%A"=="DB_USER" set "DB_USER=%%B"
            if "%%A"=="DB_PASSWORD" set "DB_PASSWORD=%%B"
        )
    )
)

set "MYSQL_AUTH=-h %DB_HOST% -u %DB_USER%"
if not "%DB_PASSWORD%"=="" set "MYSQL_AUTH=%MYSQL_AUTH% -p%DB_PASSWORD%"

REM ---- [0.5/4] Memeriksa ekstensi PHP wajib (pdo_mysql, curl) ----
echo [0.5/4] Memeriksa ekstensi PHP ...
set "EKSTENSI_HILANG="
%PHP_CMD% -m 2>nul | findstr /i "pdo_mysql" >nul || set "EKSTENSI_HILANG=!EKSTENSI_HILANG! pdo_mysql"
%PHP_CMD% -m 2>nul | findstr /i "curl" >nul || set "EKSTENSI_HILANG=!EKSTENSI_HILANG! curl"
if not "!EKSTENSI_HILANG!"=="" (
    echo     PERINGATAN: Ekstensi PHP berikut belum aktif:!EKSTENSI_HILANG!
    echo     Tanpa ini, halaman akan gagal total ^('could not find driver'^) atau
    echo     unggah desain di admin akan error ^('curl_init^(^) tidak ditemukan'^).
    echo     Cara aktifkan di XAMPP/Laragon: buka file php.ini, hapus tanda ';' di
    echo     depan baris extension=pdo_mysql dan extension=curl, lalu restart Apache/PHP.
    echo     Skrip tetap dilanjutkan, tapi sistem kemungkinan tidak akan berjalan normal.
) else (
    echo     Ekstensi pdo_mysql ^& curl sudah aktif.
)

REM ---- [1/4] Menyiapkan database secara otomatis (kalau belum ada) ----
echo [1/4] Memeriksa database ...
where mysql >nul 2>nul
if errorlevel 1 (
    echo     PERINGATAN: perintah 'mysql' tidak ditemukan di PATH. Lewati pemeriksaan otomatis;
    echo     pastikan database "%DB_NAME%" sudah dibuat manual dari database\rotama_jaya_online.sql.
) else (
    set "DB_SUDAH_ADA="
    for /f "delims=" %%R in ('mysql %MYSQL_AUTH% -N -e "SHOW DATABASES LIKE '%DB_NAME%';" 2^>nul') do set "DB_SUDAH_ADA=%%R"
    if "!DB_SUDAH_ADA!"=="" (
        echo     Database "%DB_NAME%" belum ada. Membuat dari database\rotama_jaya_online.sql ...
        mysql %MYSQL_AUTH% < database\rotama_jaya_online.sql
        echo     Database dibuat dari schema dan data deployment online.
    ) else (
        echo     Database "%DB_NAME%" sudah ada, lewati pembuatan ulang.
    )

)

REM ---- [2/4] Memasang dependensi Python secara otomatis (kalau belum) ----
echo [2/4] Memeriksa dependensi Python ...
%PYTHON_CMD% -c "import flask, sklearn, tensorflow" >nul 2>nul
if errorlevel 1 (
    echo     Memasang dependensi Python dari python_service\requirements.txt ...
    pushd python_service
    %PYTHON_CMD% -m pip install -q -r requirements.txt
    popd
    echo     Dependensi Python terpasang.
) else (
    echo     Dependensi Python sudah lengkap, lewati instalasi.
)

REM ---- [3/4] Menjalankan layanan Python (Flask) & server PHP ----
echo [3/4] Menjalankan layanan Python (Flask) di port 5000 ...
start "Rotama Jaya - Layanan Python" cmd /k "cd python_service && %PYTHON_CMD% aplikasi_layanan.py"

timeout /t 3 /nobreak >nul

echo     Menjalankan server PHP di http://localhost:8000 ...
start "Rotama Jaya - Server PHP" cmd /k ""%PHP_CMD%" -S localhost:8000"

timeout /t 2 /nobreak >nul

REM ---- [4/4] Membuka browser ----
echo [4/4] Membuka browser di halaman index ...
start http://localhost:8000/index.php

echo.
echo Sistem berjalan sepenuhnya otomatis. Jangan tutup dua jendela terminal yang
echo baru terbuka (Layanan Python dan Server PHP) selama masih ingin memakai sistem.
echo Satu-satunya langkah manual yang tersisa: login ke /admin dan input data
echo desain lewat menu "Kelola Desain".

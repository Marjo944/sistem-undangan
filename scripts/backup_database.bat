@echo off
REM =====================================================
REM BACKUP_DATABASE.BAT
REM Mencadangkan database MySQL/MariaDB (mysqldump) dan folder unggahan
REM (uploads\desain, uploads\produksi) ke satu file .zip per hari.
REM
REM CARA PAKAI MANUAL: klik dua kali file ini, atau jalankan dari terminal.
REM
REM CARA MENJADWALKAN OTOMATIS (Windows Task Scheduler, tiap hari jam 02:00):
REM   1. Buka "Task Scheduler" -> "Create Basic Task..."
REM   2. Trigger: Daily, jam 02:00
REM   3. Action: "Start a program" -> arahkan ke file .bat ini
REM   4. "Start in": isi dengan folder tempat file ini berada (folder scripts\)
REM
REM Butuh: mysqldump.exe ada di PATH (biasanya sudah otomatis di XAMPP/Laragon,
REM kalau tidak, tambahkan folder mysql\bin ke PATH sistem terlebih dahulu),
REM dan PowerShell (bawaan Windows 10/11, dipakai untuk membuat .zip).
REM =====================================================
setlocal enabledelayedexpansion
cd /d "%~dp0\.."

REM ---- Nilai bawaan (isi manual di sini kalau tidak pakai .env) ----
set DB_HOST=localhost
set DB_NAME=rotama_jaya
set DB_USER=root
set DB_PASSWORD=

REM ---- Baca .env kalau ada (baris sederhana KUNCI=NILAI) ----
if exist .env (
    for /f "usebackq tokens=1,2 delims==" %%A in (".env") do (
        if "%%A"=="DB_HOST" set DB_HOST=%%B
        if "%%A"=="DB_NAME" set DB_NAME=%%B
        if "%%A"=="DB_USER" set DB_USER=%%B
        if "%%A"=="DB_PASSWORD" set DB_PASSWORD=%%B
    )
)

set RETENSI_HARI=14
set DIREKTORI_BACKUP=backups
if not exist "%DIREKTORI_BACKUP%" mkdir "%DIREKTORI_BACKUP%"

for /f "tokens=1-4 delims=/ " %%a in ('date /t') do set TGL=%%c-%%a-%%b
set JAM=%time: =0%
set JAM=%JAM::=-%
set JAM=%JAM:.=-%
set TANGGAL=%TGL%_%JAM:~0,8%

set STAGING=%TEMP%\rotama_backup_staging
if exist "%STAGING%" rmdir /s /q "%STAGING%"
mkdir "%STAGING%"

echo [1/3] Mencadangkan database '%DB_NAME%'...
if "%DB_PASSWORD%"=="" (
    mysqldump -h %DB_HOST% -u %DB_USER% --single-transaction --routines --triggers %DB_NAME% > "%STAGING%\database_%DB_NAME%.sql"
) else (
    mysqldump -h %DB_HOST% -u %DB_USER% -p%DB_PASSWORD% --single-transaction --routines --triggers %DB_NAME% > "%STAGING%\database_%DB_NAME%.sql"
)

echo [2/3] Menyalin unggahan (desain + produksi) dan model TF-IDF...
if exist "uploads\desain" xcopy /e /i /q "uploads\desain" "%STAGING%\uploads\desain\" >nul
if exist "uploads\produksi" xcopy /e /i /q "uploads\produksi" "%STAGING%\uploads\produksi\" >nul
if exist "python_service\model_tfidf.pkl" (
    if not exist "%STAGING%\python_service" mkdir "%STAGING%\python_service"
    copy /y "python_service\model_tfidf.pkl" "%STAGING%\python_service\" >nul
)

set FILE_BACKUP=%DIREKTORI_BACKUP%\rotama_jaya_backup_%TANGGAL%.zip
powershell -NoProfile -Command "Compress-Archive -Path '%STAGING%\*' -DestinationPath '%FILE_BACKUP%' -Force"
echo     -^> Tersimpan: %FILE_BACKUP%

rmdir /s /q "%STAGING%"

echo [3/3] Menghapus cadangan lebih tua dari %RETENSI_HARI% hari...
forfiles /p "%DIREKTORI_BACKUP%" /m rotama_jaya_backup_*.zip /d -%RETENSI_HARI% /c "cmd /c del @path" 2>nul

echo.
echo Selesai.
echo PENTING: salin folder 'backups' secara berkala ke penyimpanan TERPISAH
echo (Google Drive, hard disk eksternal, dll) supaya tetap aman.
endlocal

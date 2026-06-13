@echo off
setlocal EnableDelayedExpansion
title Unistock App - Setup
color 0A

echo.
echo  =============================================
echo    UNISTOCK APP - SETUP (ONE CLICK INSTALL)
echo  =============================================
echo.

:: Dapatkan folder saat ini
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"
for %%I in ("%SCRIPT_DIR%") do set "FOLDER_NAME=%%~nxI"
set "APP_URL=http://localhost/%FOLDER_NAME%"

echo  Folder : %FOLDER_NAME%
echo  Path   : %SCRIPT_DIR%
echo  URL    : %APP_URL%
echo.

:: ── Step 1: Cek XAMPP MySQL ──────────────────────────────────
echo  [1/3] Mencari XAMPP MySQL...
set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" (
    echo  [ERROR] XAMPP MySQL tidak ditemukan di C:\xampp
    echo.
    echo  Solusi:
    echo    1. Install XAMPP dari https://www.apachefriends.org
    echo    2. Jalankan install.bat kembali
    echo.
    pause & exit /b 1
)
echo         OK - Ditemukan di %MYSQL%
echo.

:: ── Step 2: Test koneksi MySQL ──────────────────────────────
echo  [2/3] Menghubungkan ke MySQL...
"%MYSQL%" -u root -e "SELECT 1;" >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] Tidak bisa terhubung ke MySQL!
    echo.
    echo  Solusi:
    echo    1. Buka XAMPP Control Panel
    echo    2. Klik tombol [Start] di baris MySQL
    echo    3. Tunggu sampai status hijau
    echo    4. Jalankan install.bat kembali
    echo.
    start "" "C:\xampp\xampp-control.exe" 2>nul
    pause & exit /b 1
)
echo         OK - Koneksi MySQL berhasil
echo.

:: ── Step 3: Import database ──────────────────────────────────
echo  [3/3] Mengimport database unistock...
set "SQL_FILE=%SCRIPT_DIR%\database\unistock.sql"
if not exist "%SQL_FILE%" (
    echo  [ERROR] File SQL tidak ditemukan:
    echo          %SQL_FILE%
    pause & exit /b 1
)
"%MYSQL%" -u root < "%SQL_FILE%"
if %errorlevel% neq 0 (
    echo  [ERROR] Import database gagal!
    echo          Coba jalankan sebagai Administrator ^(klik kanan → Run as administrator^)
    pause & exit /b 1
)
echo         OK - Database berhasil diimport
echo.

:: ── Selesai ──────────────────────────────────────────────────
echo  =============================================
echo    SETUP SELESAI!
echo  =============================================
echo.
echo  Aplikasi siap diakses di:
echo    %APP_URL%
echo.
echo  Membuka browser dalam 3 detik...
timeout /t 3 /nobreak >nul
start "" "%APP_URL%"
echo.
endlocal
pause

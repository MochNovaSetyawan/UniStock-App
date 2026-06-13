@echo off
setlocal EnableDelayedExpansion
title Unistock App - Backup Tool
color 0B

echo.
echo  =============================================
echo    UNISTOCK APP - BACKUP TOOL
echo  =============================================
echo.

:: Setup path
set "SCRIPT_DIR=%~dp0"
set "SCRIPT_DIR=%SCRIPT_DIR:~0,-1%"

:: Generate timestamp dari wmic
for /f "tokens=2 delims==" %%a in ('wmic OS Get localdatetime /value 2^>nul') do (
    if not "%%a"=="" set "DT=%%a"
)
set "TIMESTAMP=%DT:~0,8%_%DT:~8,6%"

:: Resolusi path absolut untuk file backup (di folder parent)
for %%F in ("%SCRIPT_DIR%\..\Unistock-Backup-%TIMESTAMP%.zip") do set "BACKUP_FILE=%%~fF"

echo  Akan membuat: Unistock-Backup-%TIMESTAMP%.zip
echo  Lokasi      : %BACKUP_FILE%
echo.

:: ── Step 1: Cek mysqldump ────────────────────────────────────
echo  [1/3] Mengecek XAMPP...
set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
if not exist "%MYSQLDUMP%" (
    echo  [ERROR] XAMPP tidak ditemukan di C:\xampp
    echo          Install XAMPP dari https://www.apachefriends.org
    pause & exit /b 1
)
echo         OK - XAMPP ditemukan
echo.

:: ── Step 2: Export database ──────────────────────────────────
echo  [2/3] Mengexport database unistock...
"%MYSQLDUMP%" -u root --databases unistock --add-drop-database --routines --triggers --single-transaction > "%SCRIPT_DIR%\database\unistock.sql"
if %errorlevel% neq 0 (
    echo  [ERROR] Export database gagal!
    echo          Pastikan MySQL sudah berjalan di XAMPP Control Panel.
    pause & exit /b 1
)
echo         OK - Database berhasil diexport ke database\unistock.sql
echo.

:: ── Step 3: Buat file ZIP ────────────────────────────────────
echo  [3/3] Membuat file ZIP backup...
echo         (proses ini mungkin membutuhkan beberapa detik)
powershell -NoProfile -Command "$src='%SCRIPT_DIR%'; $dest='%BACKUP_FILE%'; $excl=@('.git','.playwright-mcp','.claude','backup.bat'); $items=Get-ChildItem $src | Where-Object {$_.Name -notin $excl}; if($items.Count -gt 0){Compress-Archive -Path @($items.FullName) -DestinationPath $dest -Force; Write-Host '         OK - ZIP berhasil dibuat'} else {Write-Host '[ERROR] Tidak ada file yang ditemukan'; exit 1}"

if %errorlevel% neq 0 (
    echo  [ERROR] Pembuatan ZIP gagal!
    pause & exit /b 1
)

:: ── Selesai ──────────────────────────────────────────────────
echo.
echo  =============================================
echo    BACKUP SELESAI!
echo  =============================================
echo.
echo  File backup: Unistock-Backup-%TIMESTAMP%.zip
echo.
echo  ── Cara Restore ──────────────────────────────
echo.
echo    1. Copy file ZIP ke komputer tujuan
echo    2. Pastikan XAMPP sudah terinstall
echo    3. Extract ZIP ke folder htdocs:
echo       Contoh: C:\xampp\htdocs\Unistock-App\
echo    4. Start Apache dan MySQL di XAMPP Control Panel
echo    5. Double-click file install.bat
echo    6. Browser akan terbuka otomatis!
echo.
echo  Membuka folder lokasi backup...
for %%F in ("%BACKUP_FILE%") do explorer /select,"%%~fF"

endlocal
pause

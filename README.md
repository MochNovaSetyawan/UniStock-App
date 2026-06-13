# UniStock App

UniStock adalah aplikasi web inventaris kampus untuk mengelola data barang, kategori, lokasi, unit barang, peminjaman, pengembalian, maintenance, laporan, notifikasi, pesan, dan manajemen pengguna. Aplikasi ini dibuat dengan PHP native dan database MySQL/MariaDB.

## Fitur Utama

- Login multi-role: superadmin, admin, dan worker.
- Manajemen inventaris barang dan unit barang.
- Pencatatan lokasi dan kategori aset.
- Alur peminjaman, persetujuan, pengembalian, dan riwayat transaksi.
- Modul maintenance/perbaikan barang.
- Laporan, audit log, notifikasi, dan pesan antar pengguna.

## Akun Demo

Database bawaan sudah berisi akun berikut:

| Role | Username | Password |
| --- | --- | --- |
| Super Admin | `superadmin` | `password` |
| Admin | `admin` | `password` |
| Worker | `worker1` | `password` |

## Instalasi Windows dengan XAMPP

Cara ini cocok jika ingin menjalankan aplikasi seperti PHP project biasa di Windows.

### Prasyarat

- XAMPP dengan Apache dan MySQL/MariaDB.
- Repository ini sudah diekstrak atau di-clone.

### Langkah Instalasi

1. Install XAMPP dari halaman resmi Apache Friends.
2. Copy folder project ke:

   ```text
   C:\xampp\htdocs\UniStock-App
   ```

3. Buka XAMPP Control Panel.
4. Start service `Apache` dan `MySQL`.
5. Jalankan file:

   ```text
   install.bat
   ```

   Script ini akan mengimport database dari `database/unistock.sql`.

6. Buka aplikasi di browser:

   ```text
   http://localhost/UniStock-App
   ```

### Konfigurasi Manual Windows

Jika tidak memakai `install.bat`, import database secara manual:

1. Buka phpMyAdmin:

   ```text
   http://localhost/phpmyadmin
   ```

2. Import file:

   ```text
   database/unistock.sql
   ```

3. Pastikan konfigurasi database di `bootstrap.php` sesuai:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'unistock');
   ```

## Instalasi Linux dengan Docker

Cara ini menjalankan aplikasi PHP Apache dan database MariaDB lewat Docker.

### Prasyarat

- Docker.
- Docker Compose.

Jika memakai WSL, aktifkan integrasi WSL di Docker Desktop:

```text
Docker Desktop > Settings > Resources > WSL integration
```

### Menjalankan Aplikasi

Masuk ke folder project:

```bash
cd UniStock-App
```

Jalankan script:

```bash
./run-docker.sh
```

Setelah selesai, buka:

```text
http://localhost:8080
```

Database MariaDB tersedia dari host di:

```text
Host: localhost
Port: 3307
Database: unistock
Username: unistock
Password: unistock
```

### Perintah Docker yang Berguna

Melihat log:

```bash
docker compose logs -f
```

Menghentikan aplikasi:

```bash
docker compose down
```

Reset database dan import ulang dari `database/unistock.sql`:

```bash
docker compose down -v
./run-docker.sh
```

## Struktur Penting

```text
app/                 Core, model, helper, dan service aplikasi
assets/css/          File CSS aplikasi
database/            Dump database bawaan
includes/            Header, footer, dan endpoint kecil global
modules/             Modul fitur aplikasi
bootstrap.php        Konfigurasi utama aplikasi
docker-compose.yml   Setup Docker app dan database
run-docker.sh        Script menjalankan aplikasi dengan Docker
install.bat          Script instalasi database untuk Windows/XAMPP
backup.bat           Script backup untuk Windows/XAMPP
```

## Catatan

- Nama institusi default adalah `Universitas Esa Unggul`.
- File upload disimpan di `assets/img/uploads/`.
- Untuk Docker, `run-docker.sh` akan membuat folder upload jika belum ada.
- Jika database Docker sudah pernah dibuat, perubahan pada `database/unistock.sql` tidak otomatis diimport ulang kecuali volume database dihapus dengan `docker compose down -v`.

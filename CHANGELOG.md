# Changelog

Semua perubahan penting pada Rangkui dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [Unreleased]

## [1.0.1] - 2026-11-24

Rilis keamanan dan stabilisasi. **Semua pengguna sangat disarankan untuk memperbarui.**

### Keamanan
- **Kritis:** injeksi SQL tanpa login di pencarian publik (`beranda/search`) dan halaman detail (`beranda/detail/...`). Semua query publik kini memakai parameter binding dan daftar kolom yang diizinkan.
- **Kritis:** beberapa halaman admin dapat diakses tanpa login (`membership/*`, `profile/*`, `export/*`, `home/stats`). Filter login kini berlaku global dengan daftar pengecualian khusus halaman publik, sehingga modul baru otomatis terlindungi.
- **Kritis:** unggahan berkas arbitrer yang dapat dieksekusi (foto anggota, sampul, foto pustakawan, lampiran). Jenis berkas kini ditentukan dari isi berkas, nama berkas diacak, dan folder `public/uploads` tidak dapat menjalankan skrip.
- **Kritis:** `profile/save` mengambil `user_id` dari formulir sehingga siapa pun dapat mengganti password admin dan menaikkan hak aksesnya sendiri. Kini selalu memakai pengguna dari sesi, dan `user_type` tidak dapat diubah dari halaman profil.
- **Tinggi:** perlindungan CSRF diaktifkan untuk semua request yang mengubah data. Token disisipkan otomatis ke formulir dan AJAX (`public/assets/custom/js/csrf.js`). Aksi hapus log, hapus/re-indeks, dan backup kini memakai POST.
- **Tinggi:** XSS tersimpan. Lebih dari 400 output di view kini di-escape, data JSON di atribut `onclick` di-encode dengan aman, dan pesan notifikasi tidak lagi disisipkan mentah ke JavaScript.
- Login: throttle 5 percobaan/5 menit per IP, regenerasi ID sesi, pencatatan `last_login`, rehash password otomatis, dan **wajib ganti password** bila masih memakai password bawaan/lemah.
- Instalasi baru tidak lagi membuat akun `admin/admin`: kredensial admin diisi di wizard (min. 8 karakter).
- Lampiran PDF disimpan di `writable/uploads/repository` dan disajikan lewat `/beranda/file/{biblio}/{file}` yang memeriksa hak akses (publik/privat/terbatas, koleksi tersembunyi). Penghitung unduhan tidak lagi bisa dipalsukan.
- `extract($_POST)` diganti dengan whitelist field. Penulisan `.env` aman dari injeksi baris. Nama tabel di `addOptions` di-whitelist. `unserialize()` dibatasi ke `stdClass`.
- Backup: kredensial database tidak lagi terlihat di daftar proses, folder tidak lagi `0777`, dan unduhan dibatasi ke folder backup.

### Diperbaiki
- Backup database tidak berjalan di Linux (memakai perintah Windows `where`). Kini mencari `mysqldump`/`mariadb-dump` lintas platform, hasil di-gzip, dan bisa diatur lewat `backup.mysqldumpPath`.
- Lampiran yang diunggah saat **menambah** dokumen diabaikan; hanya formulir edit yang memprosesnya.
- Indeks pencarian tidak diperbarui saat dokumen ditambah, diubah, atau dihapus. Kini diperbarui otomatis per dokumen.
- Nama dengan apostrof tersimpan dengan backslash (`O\'Brien`) karena escape ganda di indeks, pustakawan, dan profil.
- Berkas sampul/lampiran tidak terhapus saat dokumen dihapus di server Linux (pemisah path Windows).
- Menambah satu pintasan menu membuat seluruh panel admin error 500.
- Halaman edit pustakawan error bila data media sosial kosong. Halaman profil selalu gagal menyimpan.
- Ekspor XML/MODS detail gagal bila data mengandung `&`.
- Pencarian dan daftar hasil error bila tanggal/abstrak kosong.
- Respons hapus dokumen tercemar output sehingga tabel tidak memuat ulang.
- Wizard upgrade: pemetaan hak akses grup dari modul SLiMS lama tidak pernah dijalankan (query tertimpa sebelum dieksekusi).
- Wizard: cek koneksi gagal di hosting yang tidak memberi akses `mysql.user`; dump SQL dipecah salah bila data berisi `;`; URL absolut membuat instalasi di subfolder gagal; password database dikirim balik ke browser.
- Validasi anggota menolak formulir tanpa password; tanggal kedaluwarsa anggota selalu ditimpa (`=` alih-alih `==`).
- Repositori tidak menyertakan `writable/logs` dan `writable/cache` sehingga clone baru langsung error 500.
- Pemeriksaan MongoDB memakai `php -i` (memeriksa PHP CLI, bukan web server).

### Ditambahkan
- **OAI-PMH 2.0 lengkap** di `/oai`: keenam verb, semua kode error, `resumptionToken` (bertanda tangan HMAC, tanpa state), filter `from`/`until`, set per jenis karya (`type:BS|THS|DSR`) dan program studi (`prodi:<kode>`), identifier `oai:<domain>:<id>`, datestamp UTC, serta Dublin Core yang diperkaya kosakata `info:eu-repo` (jenis tesis & hak akses) agar ramah OpenAIRE/BASE/Garuda. Koleksi tersembunyi tidak diekspos. Tervalidasi terhadap XSD resmi OAI-PMH.
- Tes otomatis: unit test, tes regresi keamanan rute (login & CSRF), dan smoke test end-to-end (`tests/e2e/smoke.sh`).
- GitHub Actions: lint PHP 8.1–8.4, PHPUnit, instalasi + smoke test dengan MariaDB, dan rilis otomatis berupa zip siap pasang (berisi `vendor/`).
- `Makefile`: `make serve`, `make test`, `make lint`, `make release`.

### Diubah
- Satu `app/Config/Routes.php` untuk mode instalasi dan mode aplikasi; wizard tidak lagi menimpa berkas kode. Penanda instalasi: `writable/installed.lock`.
- `vendor/` tidak lagi disimpan di git. Gunakan `composer install` atau unduh zip rilis.
- `mongodb/mongodb` kini opsional (hanya untuk `INDEX_TYPE=nosql`).
- `.env.example` ditulis ulang (bawaan `production`, kunci `security.*` & `oai.*`). Nama cookie CSRF menjadi `csrf_cookie_rangkui`.
- Dihapus: `admin-example/`, `public/index.php.old`, `app/Config/Routes.local.php`, `app/Config/Routes-installer.php`.

### Panduan upgrade dari 1.0.0
1. **Backup** database dan folder aplikasi.
2. Bila memakai git, kembalikan dulu `Routes.php` yang dulu ditimpa installer, lalu tarik pembaruan:
   ```bash
   git checkout -- app/Config/Routes.php
   git pull origin main
   composer install --no-dev
   ```
   Bila tidak memakai git: unduh `rangkui-v1.0.1.zip` dari halaman Releases, lalu timpa semua berkas **kecuali** `.env`, `writable/`, dan `public/uploads/`.
3. Pastikan folder `writable/cache`, `writable/logs`, `writable/session`, `writable/backup`, dan `writable/uploads/repository` ada dan dapat ditulis web server.
4. (Disarankan) pindahkan lampiran lama agar tidak bisa diakses langsung:
   ```bash
   mv public/uploads/repository/*.pdf writable/uploads/repository/
   ```
   Rangkui mencari lampiran di kedua lokasi, jadi langkah ini aman dilakukan kapan saja. Di Apache, akses langsung ke `public/uploads/repository` sudah diblokir oleh `.htaccess`.
5. **Nginx** tidak membaca `.htaccess`. Tambahkan ke blok `server`:
   ```nginx
   location ^~ /uploads/repository/ { deny all; }
   location ~* ^/uploads/.*\.(php\d?|phtml|phar|pl|py|cgi|sh)$ { deny all; }
   ```
6. Jalankan **Sistem → Indeks Bibliografi → Re-index** sekali agar data indeks lama bebas escape ganda.
7. Pengguna dengan password bawaan/lemah akan diminta menggantinya saat login berikutnya.

[Unreleased]: https://github.com/setiadifoss/Rangkui/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/setiadifoss/Rangkui/releases/tag/v1.0.1

# Rangkui

[![CI](https://github.com/setiadifoss/Rangkui/actions/workflows/ci.yml/badge.svg)](https://github.com/setiadifoss/Rangkui/actions/workflows/ci.yml)
[![Lisensi: MIT](https://img.shields.io/badge/Lisensi-MIT-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![OAI-PMH](https://img.shields.io/badge/OAI--PMH-2.0-orange)

**Rangkui** adalah sistem repositori institusi dan ETD (*Electronic Theses & Dissertations*): skripsi, tesis, dan disertasi. Rangkui merupakan generasi terbaru **Setiadi**, dikembangkan oleh [Setiadifoss](https://github.com/setiadifoss) untuk perpustakaan perguruan tinggi di Indonesia, dengan prinsip arsitektur bersih, kolaborasi terbuka, dan inovasi berbasis komunitas.

*Rangkui is an open-source institutional repository and ETD system for Indonesian academic libraries, built on CodeIgniter 4, SLiMS-schema compatible, with an OPAC and a full OAI-PMH 2.0 endpoint.*

## Fitur utama

- **Katalog karya ilmiah**: penulis, pembimbing (1–3), penguji, kontributor, subjek, kode program studi Kemdikbud, lisensi, dan hak cipta.
- **Lampiran berkas** dengan hak akses publik, privat, atau terbatas, serta statistik unduhan.
- **OPAC** dengan pencarian dan halaman detail, plus ekspor MODS.
- **OAI-PMH 2.0** (`/oai`) dengan Dublin Core yang ramah OpenAIRE/BASE/Garuda, set per jenis karya & prodi, dan *resumptionToken*.
- **Kompatibel SLiMS**: wizard upgrade dari basis data SLiMS/Setiadi.
- Keanggotaan, laporan & visualisasi, manajemen pustakawan & grup akses, backup basis data, log sistem.
- Indeks pencarian MySQL (bawaan) atau MongoDB (opsional).

## Persyaratan

- PHP **8.1+** dengan ekstensi `intl`, `mbstring`, `mysqli`, `fileinfo`, `gd`, `curl`
- MySQL 5.7+/8.x atau MariaDB 10.3+
- Apache (dengan `mod_rewrite`) atau Nginx
- Opsional: `mysqldump` untuk fitur backup; ekstensi `mongodb` untuk `INDEX_TYPE=nosql`

## Instalasi cepat

### A. Dari paket rilis (disarankan untuk hosting)

1. Unduh `rangkui-vX.Y.Z.zip` terbaru dari [Releases](https://github.com/setiadifoss/Rangkui/releases) dan ekstrak ke server.
2. Arahkan *document root* web server ke folder **`public/`**.
3. Pastikan `writable/` dan `public/uploads/` dapat ditulis web server.
4. Buka situs di peramban, lalu ikuti wizard: isi koneksi database dan **akun administrator**.

### B. Dari git (untuk pengembang)

```bash
git clone https://github.com/setiadifoss/Rangkui.git
cd Rangkui
composer install
make serve            # http://localhost:8080 → wizard instalasi
```

Setelah instalasi, wizard membuat `.env`, menulis `writable/installed.lock`, dan menghapus folder `admin/`.

### Nginx

Nginx tidak membaca `.htaccess`. Tambahkan ke blok `server`:

```nginx
root /path/to/rangkui/public;
location / { try_files $uri $uri/ /index.php$is_args$args; }
location ^~ /uploads/repository/ { deny all; }
location ~* ^/uploads/.*\.(php\d?|phtml|phar|pl|py|cgi|sh)$ { deny all; }
```

## OAI-PMH

Endpoint: `https://<domain-anda>/oai`

```
/oai?verb=Identify
/oai?verb=ListSets
/oai?verb=ListRecords&metadataPrefix=oai_dc&set=type:THS
/oai?verb=GetRecord&metadataPrefix=oai_dc&identifier=oai:<domain-anda>:123
```

Atur `oai.repositoryIdentifier` dan `oai.adminEmail` di `.env` sebelum mendaftarkan endpoint ke harvester.

## Pengembangan

```bash
make lint      # cek sintaks PHP
make test      # PHPUnit
make release   # bangun zip rilis ke dist/
```

Smoke test end-to-end (instalasi, keamanan, unggahan, OAI-PMH) ada di `tests/e2e/smoke.sh` dan dijalankan otomatis di GitHub Actions.

## Keamanan

Laporkan kerentanan secara privat; lihat [SECURITY.md](SECURITY.md). Riwayat perubahan dan panduan upgrade ada di [CHANGELOG.md](CHANGELOG.md).

## Lisensi

[MIT](LICENSE) © Setiadifoss

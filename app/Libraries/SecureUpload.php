<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Penyimpanan berkas unggahan yang aman.
 *
 * - Ekstensi ditentukan dari ISI berkas (MIME hasil deteksi server), bukan dari nama berkas klien.
 * - Hanya MIME/ekstensi dalam daftar putih yang diterima.
 * - Gambar diverifikasi dengan getimagesize(); PDF diverifikasi dari magic bytes "%PDF-".
 * - Nama berkas acak sehingga tidak bisa ditebak atau menimpa berkas lain.
 */
class SecureUpload
{
    public const IMAGE = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public const PDF = [
        'application/pdf' => 'pdf',
    ];

    private ?string $error = null;

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Simpan berkas gambar. Mengembalikan nama berkas atau null bila tidak ada/ditolak.
     */
    public function storeImage(?UploadedFile $file, string $dir, string $prefix, int $maxKb = 2048): ?string
    {
        return $this->store($file, $dir, $prefix, self::IMAGE, $maxKb, 'image');
    }

    /**
     * Simpan berkas PDF. Mengembalikan nama berkas atau null bila tidak ada/ditolak.
     */
    public function storePdf(?UploadedFile $file, string $dir, string $prefix, int $maxKb = 51200): ?string
    {
        return $this->store($file, $dir, $prefix, self::PDF, $maxKb, 'pdf');
    }

    /**
     * Apakah ada berkas yang benar-benar dikirim pada field ini?
     */
    public static function wasSubmitted(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @param array<string,string> $allowed peta MIME => ekstensi
     */
    public function store(?UploadedFile $file, string $dir, string $prefix, array $allowed, int $maxKb, string $kind): ?string
    {
        $this->error = null;

        if (! self::wasSubmitted($file)) {
            return null;
        }

        if (! $file->isValid() || $file->hasMoved()) {
            $this->error = 'Unggahan gagal: ' . $file->getErrorString();

            return null;
        }

        if ($file->getSize() > $maxKb * 1024) {
            $this->error = "Ukuran berkas melebihi batas {$maxKb} KB.";

            return null;
        }

        // getMimeType() memakai finfo terhadap isi berkas, bukan header dari klien.
        $mime = strtolower((string) $file->getMimeType());
        if (! isset($allowed[$mime])) {
            $this->error = 'Jenis berkas tidak diizinkan.';

            return null;
        }

        $tmp = $file->getTempName();
        if ($kind === 'image' && @getimagesize($tmp) === false) {
            $this->error = 'Berkas bukan gambar yang valid.';

            return null;
        }
        if ($kind === 'pdf') {
            $head = (string) @file_get_contents($tmp, false, null, 0, 5);
            if ($head !== '%PDF-') {
                $this->error = 'Berkas bukan PDF yang valid.';

                return null;
            }
        }

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error = 'Folder unggahan tidak dapat dibuat.';

            return null;
        }

        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '', $prefix) ?: 'file';
        $name   = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];

        $file->move($dir, $name);

        return $name;
    }
}

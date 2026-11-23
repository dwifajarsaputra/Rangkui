<?php
if (!function_exists('formatString')) {
    function formatString($input, $is_uppercase = null)
    {
        if (!is_null($is_uppercase)) {
            $output = strtoupper($input);
        } else {
            $input = str_replace('_', ' ', $input);
            $output = ucwords($input);
        }
        return $output;
    }
}

function slim_alert($status = 'success', $message = 'Berhasil!', $text = null)
{
    $session = session();
    $alert   = ['status' => $status, 'msg' => $message, 'text' => $text];
    $session->setFlashdata('alert', $alert);
    return true;
}

/**
 * @param $date
 * @param $format
 * @return mixed
 */
function TanggalIndo($date, $format = '')
{
    $BulanIndo = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    $split     = explode('-', substr((string) $date, 0, 10));
    if (count($split) !== 3 || ! isset($BulanIndo[(int) $split[1]]) || (int) $split[1] === 0) {
        return '-';
    }
    if ($format == 'd M Y') {
        return $split[0] . ' ' . $BulanIndo[(int) $split[1]] . ' ' . $split[2];
    }

    return $split[2] . ' ' . $BulanIndo[(int) $split[1]] . ' ' . $split[0];
}

/**
 * Format "YYYY-MM-DD[ HH:MM:SS]" menjadi "DD Mmm YYYY". Mengembalikan "-" untuk tanggal kosong/tidak valid.
 *
 * @param $date
 * @return string
 */
function datetimeIdn($date)
{
    $BulanIndo = ["", "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
    $spli      = explode('-', substr((string) $date, 0, 10));
    if (count($spli) !== 3 || (int) $spli[1] < 1 || (int) $spli[1] > 12) {
        return '-';
    }

    return $spli[2] . ' ' . $BulanIndo[(int) $spli[1]] . ' ' . $spli[0];
}

function generateRandomID()
{
    $characters = '0123456789'; // Hanya karakter angka
    $randomID = '';

    // Buat 16 karakter acak (20 - 3 untuk 3 tanda "-")
    for ($i = 0; $i < 16; $i++) {
        $randomID .= $characters[rand(0, strlen($characters) - 1)];

        // Tambahkan pemisah "-" setiap 4 karakter
        if (($i + 1) % 4 === 0 && $i < 15) {
            $randomID .= '-';
        }
    }

    return $randomID;
}

if (!function_exists('cekerror')) {
    function cekerror($db)
    {
        // $error = $db->getError();
        // log_message('error', $db); // Log error ke file log
        $test = $db->errors();
        return reset($test); // Mengembalikan pesan error pertama
    }
}

if (!function_exists('delImage')) {
    /**
     * Hapus berkas unggahan. $targetPath relatif terhadap folder uploads, mis. "images/docs" atau "repository".
     * Lampiran (repository) dicari di writable/uploads lebih dulu, lalu di public/uploads (berkas lama).
     *
     * @return bool|string true bila terhapus, false bila gagal, "-1." bila berkas tidak ada
     */
    function delImage($targetPath, $imageName)
    {
        $name = basename((string) $imageName);
        $dir  = trim(str_replace('\\', '/', (string) $targetPath), '/');
        if ($name === '' || $name === '.' || $name === '..') {
            return '-1.';
        }

        $candidates = [FCPATH . 'uploads/' . $dir . '/' . $name];
        if ($dir === 'repository') {
            array_unshift($candidates, WRITEPATH . 'uploads/repository/' . $name);
        }

        foreach ($candidates as $file_path) {
            if (is_file($file_path)) {
                return unlink($file_path);
            }
        }

        return '-1.';
    }
}

if (!function_exists('getAuthorityName')) {
    function getAuthorityName($authority_type)
    {
        switch ($authority_type) {
            case 'o':
                $result = 'Organizational Body';
                break;
            case 'c':
                $result = 'Conference';
                break;
            default:
                $result = 'Personal Name';
        }
        return $result;
    }
}

if (!function_exists('getDatas')) {
    /**
     * Fungsi untuk mengambil data dari tabel dengan kustomisasi format output.
     *
     * @param string $table Nama tabel.
     * @param string $col Kolom yang diambil.
     * @param string|null $whr Kondisi where (opsional).
     * @param string $val Nilai dari where (opsional).
     * @param string|null $operator Operator untuk where (misalnya: 'IN', '='). Default: null.
     * @param string|null $ord Kolom untuk pengurutan (opsional).
     * @param string $sort Urutan pengurutan (ASC/DESC). Default: 'ASC'.
     * @param string|null $pd Debug (print query). Default: null.
     * @param string|null $lq Debug (log query). Default: null.
     * @param string $output Jenis output yang diinginkan: 'array' atau 'row'. Default: 'array'.
     *
     * @return array|object|null Hasil query dalam bentuk array, object, atau null jika tidak ada data.
     */
    function getDatas($table, $col, $whr = null, $val = "", $operator = null, $ord = null, $sort = ' ASC ', $pd = null, $lq = null, $output = 'array')
    {

        $where = !is_null($whr) ? " WHERE {$whr} " : '';
        $db    = \Config\Database::connect();
        $sql   = "SELECT {$col} FROM {$table} {$where}";

        if (!is_null($operator)) {
            $tblwhr = $sql . ' ' . $operator . ' ' . $val . ' ' . (!is_null($ord) ? $ord . $sort : ' ');
        } else {
            $tblwhr = $sql . $val . (!is_null($ord) ? $ord . $sort : ' ');
        }

        if (!is_null($pd)) {
            pd($tblwhr, 1);
        }

        $query = $db->query($tblwhr);

        if ($query === false || $query->getNumRows() === 0) {
            return null;
        }

        if ($output === 'row') {
            $hasil = $query->getRow()->$col;
        } else {
            $hasil = $query->getResult();
        }
        if (!is_null($lq)) {
            lq();
        }
        return $hasil;
    }
}

if (! function_exists('post_only')) {
    /**
     * Ambil hanya field POST yang disebutkan (pengganti aman untuk extract($_POST)).
     * Field yang tidak dikirim tetap tidak terdefinisi, sama seperti perilaku sebelumnya.
     *
     * @param list<string> $keys
     */
    function post_only(array $keys): array
    {
        $post = service('request')->getPost() ?? [];

        return array_intersect_key(is_array($post) ? $post : [], array_flip($keys));
    }
}

if (! function_exists('env_set')) {
    /**
     * Set/ganti satu kunci di isi berkas .env secara aman.
     * Nilai selalu dikutip ganda & di-escape, baris baru dibuang (mencegah injeksi baris .env).
     */
    function env_set(string $content, string $key, ?string $value): string
    {
        $value  = str_replace(["\r", "\n", "\0"], '', (string) $value);
        $quoted = '"' . addcslashes($value, '"\\') . '"';
        $line   = $key . ' = ' . $quoted;
        $k      = preg_quote($key, '/');

        // Utamakan baris aktif; bila tidak ada, aktifkan baris yang dikomentari; bila tidak ada juga, tambahkan.
        foreach (['/^\s*' . $k . '\s*=.*$/m', '/^#\s*' . $k . '\s*=.*$/m'] as $regex) {
            if (preg_match($regex, $content) === 1) {
                $content = preg_replace_callback($regex, static fn () => $line, $content, 1);
                // Buang duplikat aktif lain agar nilai tidak ambigu.
                $first = strpos($content, $line) + strlen($line);

                return substr($content, 0, $first) . preg_replace('/^\s*' . $k . '\s*=.*\R?/m', '', substr($content, $first));
            }
        }

        return rtrim($content, "\n") . "\n" . $line . "\n";
    }
}

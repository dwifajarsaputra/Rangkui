<?php

namespace App\Modules\System\Controllers;

use App\Controllers\BaseController;
use App\Modules\System\Models\BackupModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Files\Exceptions\FileNotFoundException;
use Config\Services;

class BackupController extends BaseController
{
    public $viewPath = "Backup/";
    public function index()
    {
        $log_message = "{$this->session->name} access backup ";
        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', $log_message);

        $view = $this->viewPath . "v_index";
        $title = "Database Backup";

        $bm = new BackupModel();

        $list_backup = $bm->getBackup();


        $content = [
            'list_backup' => $list_backup,
        ];

        $js = [];
        _render($view, $title, $content, $js);
    }

    public function add()
    {
        $user = (string) $this->session->name;
        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', "{$user} trying backup");

        $backupPath = WRITEPATH . 'backup';
        if (! is_dir($backupPath) && ! @mkdir($backupPath, 0750, true) && ! is_dir($backupPath)) {
            return $this->_backupFailed('Folder backup tidak dapat dibuat: ' . $backupPath);
        }
        if (! is_writable($backupPath)) {
            return $this->_backupFailed('Folder backup tidak dapat ditulis: ' . $backupPath);
        }

        $mysqldump = $this->_findMysqldump();
        if ($mysqldump === null) {
            return $this->_backupFailed('Program mysqldump tidak ditemukan. Pasang paket mysql-client/mariadb-client, atau isi backup.mysqldumpPath di .env.');
        }

        // Kredensial lewat berkas opsi sementara (0600), BUKAN argumen command line yang terlihat di `ps`.
        $optionsFile = tempnam(sys_get_temp_dir(), 'rkdump');
        chmod($optionsFile, 0600);
        file_put_contents($optionsFile, sprintf(
            "[client]\nuser=\"%s\"\npassword=\"%s\"\nhost=\"%s\"\nport=%d\n",
            addcslashes((string) $this->db->username, '"\\'),
            addcslashes((string) $this->db->password, '"\\'),
            addcslashes((string) $this->db->hostname, '"\\'),
            (int) ($this->db->port ?: 3306)
        ));

        $sqlFile = $backupPath . DIRECTORY_SEPARATOR . 'backup_' . date('Ymd_His') . '.sql';
        $command = escapeshellarg($mysqldump)
            . ' --defaults-extra-file=' . escapeshellarg($optionsFile)
            . ' --single-transaction --quick --routines --no-create-db'
            . ' --result-file=' . escapeshellarg($sqlFile)
            . ' ' . escapeshellarg((string) $this->db->database) . ' 2>&1';

        $output = [];
        $status = 1;
        exec($command, $output, $status);
        @unlink($optionsFile);

        if ($status !== 0 || ! is_file($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            log_message('error', 'mysqldump failed: ' . implode("\n", $output));

            return $this->_backupFailed('Backup gagal: ' . (trim(implode(' ', $output)) ?: 'mysqldump keluar dengan status ' . $status));
        }

        // Kompres dengan gzip langsung dari PHP (tidak bergantung pada `tar` & chdir).
        $finalFile = $sqlFile;
        if (function_exists('gzopen')) {
            $gz = gzopen($sqlFile . '.gz', 'wb6');
            $in = fopen($sqlFile, 'rb');
            while (! feof($in)) {
                gzwrite($gz, (string) fread($in, 1048576));
            }
            fclose($in);
            gzclose($gz);
            unlink($sqlFile);
            $finalFile = $sqlFile . '.gz';
        }

        (new BackupModel())->insert([
            'user_id'     => $this->session->user_id,
            'backup_time' => date('Y-m-d H:i:s'),
            // Kolom backup_file hanya varchar(100): simpan nama berkas saja, bukan path absolut.
            'backup_file' => basename($finalFile),
        ]);

        slim_alert('success', 'Backup database berhasil: ' . basename($finalFile));
        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', "{$user} backup success");

        return redirect()->to('sistem/backups');
    }

    private function _backupFailed(string $message)
    {
        slim_alert('error', $message);
        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', $message);

        return redirect()->to('sistem/backups');
    }

    /**
     * Cari mysqldump lintas platform (dulu hanya `where mysqldump` yang khusus Windows).
     */
    private function _findMysqldump(): ?string
    {
        $configured = trim((string) env('backup.mysqldumpPath', ''));
        if ($configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $isWindows = stripos(PHP_OS_FAMILY, 'Windows') === 0;
        foreach (['mysqldump', 'mariadb-dump'] as $bin) {
            $found = trim((string) @shell_exec(($isWindows ? 'where ' : 'command -v ') . $bin . ($isWindows ? ' 2>NUL' : ' 2>/dev/null')));
            $found = strtok($found, "\r\n") ?: '';
            if ($found !== '' && is_file($found)) {
                return $found;
            }
        }

        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump', '/opt/lampp/bin/mysqldump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe', 'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    function download($id)
    {

        $backup = new BackupModel();

        $backupFile = $backup->find($id);

        if ($backupFile === null) {
            throw PageNotFoundException::forPageNotFound();
        } else {

            $filePath = (string) $backupFile->backup_file;
            if (basename($filePath) === $filePath) {
                $filePath = WRITEPATH . 'backup' . DIRECTORY_SEPARATOR . $filePath;
            }
            $realDir  = realpath(WRITEPATH . 'backup');
            $realFile = realpath((string) $filePath);

            // Hanya berkas di dalam writable/backup yang boleh diunduh.
            if ($realDir !== false && $realFile !== false && str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) && is_file($realFile)) {
                $filePath = $realFile;

                $log_message = "Download backup file";
                Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', $log_message);

                return $this->response->download($filePath, null);
            } else {

                $log_message = "Download backup file failed";
                Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'Backup', $log_message);

                slim_alert('error', 'File backup tidak ditemukan.');
                return redirect()->to('sistem/backups');
            }
        }
    }
}

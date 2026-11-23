<?php

namespace App\Controllers;

use App\Libraries\SqlImporter;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Wizard instalasi Rangkui (fresh install / upgrade dari SLiMS-Setiadi).
 *
 * Rute wizard hanya terdaftar selama aplikasi BELUM terpasang (lihat app/Config/Routes.php).
 * Setelah sukses, berkas penanda writable/installed.lock dibuat dan folder admin/ dihapus.
 */
class InstallController extends BaseController
{
    use ResponseTrait;

    public const LOCK_FILE = WRITEPATH . 'installed.lock';

    public function install()
    {
        return view('wizard/install_wizard');
    }

    public function fresh()
    {
        return $this->_step('fresh');
    }

    public function upgrade()
    {
        return $this->_step('upgrade');
    }

    public function type()
    {
        return $this->respond(['type' => $this->session->get('type')]);
    }

    public function process()
    {
        if (is_file(self::LOCK_FILE)) {
            return $this->respond(['status' => 'error', 'message' => 'Rangkui sudah terpasang.'], 403);
        }

        $config = $this->session->get('dbConfig');
        if (! is_array($config) || ($error = $this->_validateConfig($config, $this->session->get('type') === 'fresh')) !== null) {
            return $this->respond(['status' => 'error', 'message' => $error ?? 'Konfigurasi database belum diisi.']);
        }

        foreach (['cache', 'logs', 'session', 'uploads/repository', 'backup'] as $dir) {
            if (! is_dir(WRITEPATH . $dir)) {
                @mkdir(WRITEPATH . $dir, 0755, true);
            }
        }

        $isUpgrade = $this->session->get('type') === 'upgrade';

        try {
            $result = $isUpgrade
                ? $this->_installUpgrade($config)
                : $this->_installFresh($config);
        } catch (Throwable $e) {
            log_message('error', 'Install failed: ' . $e->getMessage());

            return $this->respond(['status' => 'error', 'message' => 'Instalasi gagal: ' . $e->getMessage()]);
        }

        if ($result !== true) {
            return $this->respond(['status' => 'error', 'message' => $result]);
        }

        if (! $this->_writeEnv($config)) {
            return $this->respond(['status' => 'error', 'message' => 'Gagal menulis berkas .env. Periksa izin tulis folder aplikasi.']);
        }

        file_put_contents(self::LOCK_FILE, date(DATE_ATOM) . "\n");
        $this->_deleteFolder(ROOTPATH . 'admin'); // best effort; lock file sudah menutup wizard
        $this->session->remove(['dbConfig', 'type']);

        return $this->respond([
            'status'  => 'success',
            'message' => $isUpgrade ? 'Upgrade Berhasil' : 'Installation Complete',
        ]);
    }

    // ------------------------------------------------------------------

    private function _step(string $type)
    {
        if ($this->request->getMethod() === 'GET') {
            $this->session->set('type', $type);

            return view('wizard/step_' . $type);
        }

        $config = [
            'host'           => trim((string) $this->request->getPost('host')),
            'port'           => (int) $this->request->getPost('port') ?: 3306,
            'username'       => trim((string) $this->request->getPost('username')),
            'password'       => (string) $this->request->getPost('password'),
            'database'       => trim((string) $this->request->getPost('database')),
            'admin_username' => trim((string) $this->request->getPost('admin_username')),
            'admin_password' => (string) $this->request->getPost('admin_password'),
        ];
        $this->session->set('dbConfig', $config);

        $error  = $this->_validateConfig($config, $type === 'fresh');
        $status = $error ?? ('Database Connection ' . ($this->_checkConnection($config) ? 'success' : 'failed'));

        // Jangan pernah mengirim balik password ke browser.
        $shown = [
            'host'     => $config['host'],
            'port'     => $config['port'],
            'username' => $config['username'],
            'database' => $config['database'],
        ];
        if ($type === 'fresh') {
            $shown['admin_username'] = $config['admin_username'];
        }
        $shown[$type === 'fresh' ? 'status' : 'connection'] = $status;

        return $this->respond($shown);
    }

    private function _validateConfig(array $c, bool $fresh): ?string
    {
        if ($c['host'] === '' || $c['username'] === '' || $c['database'] === '') {
            return 'Host, username, dan nama database wajib diisi.';
        }
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $c['host'])) {
            return 'Host database tidak valid.';
        }
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $c['database'])) {
            return 'Nama database hanya boleh huruf, angka, dan garis bawah.';
        }
        if ($c['port'] < 1 || $c['port'] > 65535) {
            return 'Port database tidak valid.';
        }
        if ($fresh) {
            if (! preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $c['admin_username'] ?? '')) {
                return 'Username admin 3–50 karakter (huruf, angka, titik, minus, garis bawah).';
            }
            if (strlen($c['admin_password'] ?? '') < 8) {
                return 'Password admin minimal 8 karakter.';
            }
        }

        return null;
    }

    private function _connect(array $c, ?string $database): BaseConnection
    {
        return \Config\Database::connect([
            'DSN'      => '',
            'hostname' => $c['host'],
            'username' => $c['username'],
            'password' => $c['password'],
            'database' => $database ?? '',
            'DBDriver' => 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => true,
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci',
            'port'     => (int) $c['port'],
        ], false);
    }

    private function _checkConnection(array $c): bool
    {
        try {
            // Cukup SELECT 1: user hosting biasa tidak punya akses ke tabel mysql.user.
            return (bool) $this->_connect($c, null)->simpleQuery('SELECT 1');
        } catch (Throwable $e) {
            return false;
        }
    }

    private function _runSqlFile(BaseConnection $db, string $file): void
    {
        $sql = @file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException('Berkas SQL tidak ditemukan: ' . basename($file));
        }
        foreach (SqlImporter::split($sql) as $statement) {
            $db->query($statement);
        }
    }

    /**
     * @return true|string
     */
    private function _installFresh(array $c)
    {
        $server = $this->_connect($c, null);
        // Nama database sudah divalidasi dengan regex ketat di _validateConfig().
        $server->query('CREATE DATABASE IF NOT EXISTS `' . $c['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

        $db = $this->_connect($c, $c['database']);
        if (in_array('biblio', $db->listTables(), true)) {
            return 'Database "' . $c['database'] . '" sudah berisi data. Gunakan opsi Upgrade, atau pilih database kosong.';
        }

        $this->_runSqlFile($db, ROOTPATH . 'admin/brantas.sql');

        $settings = include ROOTPATH . 'admin/setting.php';
        foreach ($settings['settings'] as $setting) {
            $db->table('setting')->insert([
                'setting_id'    => $setting['id'],
                'setting_name'  => $setting['key'],
                'setting_value' => $setting['value'],
            ]);
        }

        foreach ($settings['users'] as $user) {
            // Akun admin memakai kredensial yang diisi di wizard, bukan admin/admin.
            $user['username']      = $c['admin_username'];
            $user['passwd']        = password_hash($c['admin_password'], PASSWORD_DEFAULT);
            $user['last_login']    = null;
            $user['last_login_ip'] = null;
            $user['input_date']    = date('Y-m-d');
            $user['last_update']   = date('Y-m-d');
            $db->table('user')->insert($user);
        }

        return true;
    }

    /**
     * @return true|string
     */
    private function _installUpgrade(array $c)
    {
        $db = $this->_connect($c, $c['database']);
        if (! in_array('biblio', $db->listTables(), true)) {
            return 'Database "' . $c['database'] . '" tidak berisi data SLiMS/Setiadi untuk di-upgrade.';
        }

        $this->_runSqlFile($db, ROOTPATH . 'admin/upgrade.sql');
        $this->_migrateGroupAccess($db);

        return true;
    }

    /**
     * Padanan nama modul SLiMS (mst_module.module_name) → judul menu utama Rangkui (mst_menu.title).
     * Modul tanpa padanan (circulation, stock_take, serial_control) tidak dibawa.
     */
    private const SLIMS_MODULE_MAP = [
        'bibliography' => 'ETD',
        'membership'   => 'Membership',
        'master_file'  => 'Master File',
        'system'       => 'System',
        'reporting'    => 'Reporting',
    ];

    /**
     * Petakan hak akses grup dari modul SLiMS lama (mst_module) ke menu Rangkui (mst_menu).
     *
     * Versi lama menyusun query UPDATE ini tetapi variabelnya tertimpa sebelum dieksekusi,
     * sehingga hak akses grup non-admin tidak pernah dipetakan (ID modul lama justru menunjuk
     * menu Rangkui yang tidak terkait). Dilakukan di PHP agar portabel di MySQL & MariaDB.
     */
    private function _migrateGroupAccess(BaseConnection $db): void
    {
        $menuIdByTitle = [];
        foreach ($db->table('mst_menu')->select('id, title')->where('level', 1)->get()->getResult() as $menu) {
            $menuIdByTitle[strtolower($menu->title)] = (int) $menu->id;
        }

        $map = [];
        if (in_array('mst_module', $db->listTables(), true)) {
            foreach ($db->table('mst_module')->select('module_id, module_name')->get()->getResult() as $module) {
                $name  = strtolower((string) $module->module_name);
                $title = strtolower(self::SLIMS_MODULE_MAP[$name] ?? $name);
                if (isset($menuIdByTitle[$title])) {
                    $map[(int) $module->module_id] = $menuIdByTitle[$title];
                }
            }
        }

        $access = [];
        foreach ($db->table('group_access')->get()->getResultArray() as $row) {
            $group = (int) $row['group_id'];
            if ($group === 1) {
                continue; // admin di-set ulang di bawah
            }
            $module = $map[(int) $row['module_id']] ?? null;
            if ($module === null) {
                continue; // modul SLiMS tanpa padanan di Rangkui
            }
            $key          = $group . ':' . $module;
            $access[$key] = [
                'group_id'  => $group,
                'module_id' => $module,
                'r'         => max((int) ($access[$key]['r'] ?? 0), (int) $row['r']),
                'w'         => max((int) ($access[$key]['w'] ?? 0), (int) $row['w']),
            ];
        }

        // Grup admin (1) mendapat akses penuh ke semua menu.
        foreach ($db->table('mst_menu')->select('id')->get()->getResultArray() as $menu) {
            $access['1:' . $menu['id']] = ['group_id' => 1, 'module_id' => (int) $menu['id'], 'r' => 1, 'w' => 1];
        }

        $db->transStart();
        $db->table('group_access')->truncate();
        $db->table('group_access')->insertBatch(array_values($access));
        $db->transComplete();
    }

    private function _writeEnv(array $c): bool
    {
        $envFile = ROOTPATH . '.env';
        $content = is_file($envFile) ? file_get_contents($envFile) : @file_get_contents(ROOTPATH . '.env.example');
        if ($content === false) {
            $content = '';
        }

        $baseURL = rtrim(site_url('/'), '/') . '/';
        $values  = [
            'CI_ENVIRONMENT'              => 'production',
            'app.baseURL'                 => $baseURL,
            'database.default.hostname'   => $c['host'],
            'database.default.database'   => $c['database'],
            'database.default.username'   => $c['username'],
            'database.default.password'   => $c['password'],
            'database.default.port'       => (string) $c['port'],
            'database.default.DBDebug'    => 'false',
        ];
        if (! preg_match('/^encryption\.key\s*=\s*\S+/m', $content)) {
            $values['encryption.key'] = 'hex2bin:' . bin2hex(random_bytes(32));
        }

        foreach ($values as $key => $value) {
            $content = env_set($content, $key, $value);
        }

        // .env.example tetap utuh di repositori; .env dibuat sebagai salinan terpisah.
        return file_put_contents($envFile, $content) !== false;
    }

    private function _deleteFolder(string $folderPath): bool
    {
        if (! is_dir($folderPath)) {
            return false;
        }
        foreach (array_diff(scandir($folderPath), ['.', '..']) as $file) {
            $path = $folderPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path) ? ! $this->_deleteFolder($path) : ! @unlink($path)) {
                return false;
            }
        }

        return @rmdir($folderPath);
    }
}

<?php

namespace App\Modules\System\Controllers;

use Config\Services;
use CodeIgniter\HTTP\Request;
use CodeIgniter\API\ResponseTrait;
use App\Controllers\BaseController;
use App\Modules\System\Models\SystemModel;

class SystemController extends BaseController
{
    use ResponseTrait;

    public $viewpath = "Sistem/";

    public function __construct()
    {
        helper('array');
    }

    public function pengaturan()
    {
        $view  = $this->viewpath . "v_pengaturan_system";
        $title = "System Configuration";

        $setting = new SystemModel();
        $list    = (object) $setting->findAll();

        $list_var = [];
        foreach ($list as $key => $val) {
            $list_var[$val['setting_name']] = $val['setting_value'];
        }

        $lang      = $this->db->table('mst_language');
        $list_lang = $lang->get()->getResult();

        $publicKey  = env('recaptcha.publicKey', '');
        $privateKey = env('recaptcha.privateKey', '');

        $content = [
            'list_var'   => $list_var,
            'list_lang'  => $list_lang,
            'publicKey'  => $publicKey,
            'privateKey' => $privateKey,
        ];


        $js = [
            'assets/custom/js/modules/system/pengaturan-sistem',
        ];

        _render($view, $title, $content, $js);
    }

    function updateKey()
    {
        extract(post_only(['privatekey', 'publickey']), EXTR_SKIP);

        // Path ke file .env di root proyek
        $envPath = ROOTPATH . '.env';

        // Pastikan file .env ada
        if (!file_exists($envPath)) {
            return 'File .env not found!';
        }

        // Membaca file .env
        $envContent = file_get_contents($envPath);

        // Kunci reCAPTCHA hanya berisi huruf, angka, "-" dan "_".
        $publickey  = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($publickey ?? ''));
        $privatekey = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($privatekey ?? ''));

        $envContent = env_set($envContent, 'recaptcha.publicKey', $publickey);
        $envContent = env_set($envContent, 'recaptcha.privateKey', $privatekey);

        // Menulis kembali ke file .env
        $result = file_put_contents($envPath, $envContent);

        $log_message = "Update key failed";
        if ($result !== false) {
            $log_message = "Update key success";
            $resp = [
                'status'  => 200,
                'message' => "succes",
            ];
        } else {
            $resp = [
                'status'  => 500,
                'message' => "error",
            ];
        }

        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'System Config', $log_message);

        return $this->respond($resp);
    }

    function update()
    {
        $post = $this->input->getPost();

        $this->db->transBegin();
        $setting = new SystemModel();
        foreach ($post as $key => $val) {

            $setting->where('setting_name', $key);
            $setting->set('setting_value', serialize($val));
            $setting->update();
        }

        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            $log_message = "Save setting failed";
            slim_alert('error', $log_message);
        } else {
            $this->db->transCommit();
            $log_message = "Save setting success";
            slim_alert('success', $log_message);
        }

        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'System Config', $log_message);

        return redirect()->to('sistem/pengaturan-sistem');
    }
}

<?php

namespace App\Modules\Profile\Controllers;

use Config\Services;
use App\Controllers\BaseController;
use App\Modules\Profile\Models\ProfileModel;

class ProfileController extends BaseController
{
    public function index()
    {
        $loadModel = new ProfileModel();
        $js        = ["assets/custom/js/modules/profile/profile"];
        $view      = "profile/v_index";
        $title     = "Profile";

        $uid = $this->session->user_id;

        $data      = $loadModel->where('user_id', $uid)
            ->orderBy('user_id', 'DESC')
            ->get()
            ->getRow();

        if ($data === null) {
            return redirect()->to('logout');
        }
        $social             = @unserialize((string) $data->social_media, ['allowed_classes' => ['stdClass']]);
        $data->social_media = is_array($social) ? $social : [];
        $content['data'] = $data;
        _render($view, $title, $content, $js);
    }
    public function save()
    {
        $loadModel = new ProfileModel();
        // Selalu gunakan ID dari sesi; jangan pernah dari input formulir (mencegah IDOR).
        $uid = (int) $this->session->user_id;

        $rules = [
            'realname' => 'required|max_length[100]',
            'email'    => 'permit_empty|valid_email',
            'passwd1'  => 'permit_empty|min_length[8]',
        ];
        if ((string) $this->request->getPost('passwd1') !== '') {
            $rules['passwd2'] = 'matches[passwd1]';
        }
        if (! $this->validate($rules)) {
            slim_alert('error', implode(' ', $this->validator->getErrors()));

            return redirect()->to('profile');
        }

        $social = $this->request->getPost('social');
        $data   = [
            'realname'     => (string) $this->request->getPost('realname'),
            'email'        => (string) $this->request->getPost('email'),
            'social_media' => serialize(is_array($social) ? array_map('strval', $social) : []),
            'last_update'  => date('Y-m-d'),
        ];
        // user_type sengaja tidak bisa diubah dari halaman profil (mencegah eskalasi hak akses).

        $passwd = (string) $this->request->getPost('passwd1');
        if ($passwd !== '') {
            $data['passwd'] = password_hash($passwd, PASSWORD_DEFAULT);
            $this->session->remove('must_change_password');
        }

        $ok          = $loadModel->update($uid, $data);
        $log_message = 'Update profile ' . ($ok ? 'success' : 'failed') . " (user_id {$uid})";
        slim_alert($ok ? 'success' : 'error', $ok ? 'Data successfully saved' : 'Data failed to save');
        $this->session->set('name', $data['realname']);

        Services::writeLog(getTypeUser($this->session->user_type), $uid, 'User', $log_message);

        return redirect()->to('profile');
    }
}

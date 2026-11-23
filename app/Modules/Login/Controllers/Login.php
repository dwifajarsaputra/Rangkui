<?php

namespace App\Modules\Login\Controllers;

use App\Controllers\BaseController;
use App\Modules\Login\Models\LoginModel;

class Login extends BaseController
{
    /**
     * Maksimum percobaan login gagal per IP dalam satu jendela waktu.
     */
    private const MAX_ATTEMPTS = 5;

    private const WINDOW_SECONDS = 300;

    /**
     * Password yang dianggap bawaan/lemah sehingga pengguna dipaksa menggantinya.
     */
    private const WEAK_PASSWORDS = ['admin', 'password', '123456', '12345678', 'rangkui', 'setiadi'];

    public function index()
    {
        if ($this->session->has('user_id')) {
            return redirect()->to('home');
        }

        _renderLogin('login', 'DIFOSS', ['data' => []]);
    }

    public function login()
    {
        if ($this->session->has('user_id')) {
            return redirect()->to('home');
        }

        if (! $this->validate(['username' => 'required|max_length[50]', 'password' => 'required|max_length[200]'])) {
            return redirect()->back()->with('error', 'Username dan password wajib diisi');
        }

        $throttleKey = 'login_' . md5((string) $this->request->getIPAddress());
        $throttler   = service('throttler');
        if ($throttler->check($throttleKey, self::MAX_ATTEMPTS, self::WINDOW_SECONDS) === false) {
            return redirect()->back()->with('error', 'Terlalu banyak percobaan login. Coba lagi dalam ' . (int) $throttler->getTokenTime() . ' detik.');
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $userModel = new LoginModel();
        $user      = $userModel->where('username', $username)->first();

        if (! $user || ! password_verify($password, (string) $user['passwd'])) {
            return redirect()->back()->with('error', 'Invalid username or password');
        }

        // Berhasil: reset penghitung percobaan & cegah session fixation.
        $throttler->remove($throttleKey);
        $this->session->regenerate(true);

        $groups = @unserialize((string) $user['groups'], ['allowed_classes' => ['stdClass']]);

        $this->session->set([
            'user_id'   => $user['user_id'],
            'groups'    => is_array($groups) ? ($groups[0] ?? null) : null,
            'name'      => $user['realname'],
            'user_type' => $user['user_type'],
        ]);

        $update = [
            'last_login'    => date('Y-m-d H:i:s'),
            'last_login_ip' => $this->request->getIPAddress(),
        ];
        if (password_needs_rehash($user['passwd'], PASSWORD_DEFAULT)) {
            $update['passwd'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->db->table('user')->where('user_id', $user['user_id'])->update($update);

        if (in_array(strtolower($password), array_merge(self::WEAK_PASSWORDS, [strtolower($username)]), true)) {
            $this->session->set('must_change_password', true);
            slim_alert('warning', 'Anda masih memakai password bawaan/lemah. Silakan ganti password sekarang.');

            return redirect()->to('/profile');
        }

        return redirect()->to('/home')->with('message', 'Login successful!');
    }

    public function logout()
    {
        $this->session->destroy();

        return redirect()->to('login');
    }
}

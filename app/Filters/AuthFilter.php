<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login untuk semua halaman admin.
 *
 * Filter ini dipasang GLOBAL (lihat Config\Filters::$globals) dengan daftar pengecualian
 * untuk halaman publik, sehingga modul/rute baru otomatis terlindungi.
 */
class AuthFilter implements FilterInterface
{
    /**
     * Rute yang boleh diakses selama pengguna masih wajib mengganti password.
     */
    private const PASSWORD_CHANGE_ALLOWED = ['profile', 'profile/save', 'logout', 'login/logout'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->has('user_id')) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali.']);
            }

            return redirect()->to(site_url('login'))->with('error', 'You must logged in first');
        }

        if ($session->get('must_change_password')) {
            $path = trim(strtolower($request->getUri()->getPath()), '/');
            if (! in_array($path, self::PASSWORD_CHANGE_ALLOWED, true)) {
                slim_alert('warning', 'Demi keamanan, ganti password bawaan Anda terlebih dahulu.');

                return redirect()->to(site_url('profile'));
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}

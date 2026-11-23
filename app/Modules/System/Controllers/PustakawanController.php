<?php

namespace App\Modules\System\Controllers;

use Config\Services;
use App\Controllers\BaseController;
use App\Libraries\SecureUpload;
use App\Modules\System\Models\PustakawanModel;
use CodeIgniter\API\ResponseTrait;

class PustakawanController extends BaseController
{
    private $datetime;
    public function __construct()
    {
        $this->datetime = date('YmdHis');
    }
    use ResponseTrait;
    public $viewPath = "Pustakawan/";

    public function index()
    {
        $view = $this->viewPath . "v_index";
        $title = "Librarian";

        $pustakawan = new PustakawanModel();
        $user = $pustakawan->where('username !=', 'admin')
            ->get()
            ->getResult();

        $content = [
            'user' => $user,
        ];

        $js = [
            'assets/custom/js/modules/system/pustakawan',
        ];
        _render($view, $title, $content, $js);
    }

    function add()
    {
        $view = $this->viewPath . "v_add";
        $title = "Add new user";
        $content = [];
        $js = [];
        _render($view, $title, $content, $js);
    }

    function save()
    {
        $rules = [
            'image' => [
                'label' => 'Image File',
                'rules' => 'if_exist|is_image[image]'
                    . '|is_image[image]'
                    . '|mime_in[image,image/jpg,image/jpeg,image/png,image/webp]'
                    . '|max_size[image,1024]'
                    . '|max_dims[image,4000,4000]',
            ],
            'passwd1' => 'required|min_length[6]',
            'passwd2' => 'required|matches[passwd1]'
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', \Config\Services::validation());
        } else {
            extract(post_only(['eMail', 'groups', 'passwd1', 'realName', 'social', 'userType', 'username']), EXTR_SKIP);

            $save_name = $this->_storeUserImage();


            $now = date("Y-m-d H:i:s");

            $data = [
                'username'      => $username,
                'realname'      => $realName,
                'passwd'        => password_hash($passwd1, PASSWORD_BCRYPT),
                'email'         => $eMail,
                'user_type'     => $userType,
                'user_image'    => $save_name,
                'social_media'  => serialize($social),
                'last_login'    => null,
                'last_login_ip' => null,
                'groups'        => serialize($groups),
                'input_date'    => $now,
                'last_update'   => $now
            ];

            $user = new PustakawanModel();

            $this->db->transBegin();
            $user->insert($data);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                $log_message = "Add user failed";
                slim_alert('error', $log_message);
            } else {
                $this->db->transCommit();
                $log_message = "Add user success";
                slim_alert('success', $log_message);
            }

            Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'User', $log_message);


            return redirect()->to('sistem/pustakawan');
        }
    }

    function edit($id)
    {
        $pustakawan = new PustakawanModel();

        $list_pustakawan = $pustakawan->find($id);

        $view    = $this->viewPath . "v_edit";
        $title   = "Edit User";
        $content = [
            'list_pustakawan' => $list_pustakawan,
        ];

        $js = [
            'assets/custom/js/modules/system/pustakawan',
        ];
        _render($view, $title, $content, $js);
    }

    function update()
    {

        extract(post_only(['eMail', 'groups', 'passwd1', 'realName', 'social', 'userType', 'username', 'w_id']), EXTR_SKIP);

        $pustakawan = new PustakawanModel();

        $id = trim($w_id);
        $list_pustakawan = $pustakawan->find($id);

        if (!empty($list_pustakawan)) {
            $rules = [
                'image' => [
                    'label' => 'Image File',
                    'rules' => 'if_exist|is_image[image]'
                        . '|is_image[image]'
                        . '|mime_in[image,image/jpg,image/jpeg,image/png,image/webp]'
                        . '|max_size[image,1024]'
                        . '|max_dims[image,4000,4000]',
                ],
                'passwd1' => 'permit_empty|min_length[6]',  // Gunakan 'permit_empty' agar passwd1 tidak wajib
            ];
            // Cek apakah passwd1 diisi
            if ($this->input->getPost('passwd1')) {
                // Jika passwd1 diisi, tambahkan aturan matches untuk passwd2
                $rules['passwd2'] = 'required|matches[passwd1]';
            }

            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('validation', \Config\Services::validation());
            } else {
                $save_name = $this->_storeUserImage();

                $this->db->transBegin();

                $now = date("Y-m-d H:i:s");
                $data = [
                    'username'     => $username,
                    'realname'     => $realName,
                    'email'        => $eMail,
                    'user_type'    => $userType,
                    'social_media' => serialize($social),
                    'groups'       => serialize($groups),
                    'last_update'  => $now,
                ];

                if (!empty($passwd1)) {
                    $data['passwd'] = password_hash($passwd1, PASSWORD_BCRYPT);
                }

                if (!is_null($save_name)) {
                    $data['user_image'] = $save_name;
                }

                $pustakawan->set($data);
                $pustakawan->where('user_id', $id);
                $pustakawan->update();

                if ($this->db->transStatus() === false) {
                    $this->db->transRollback();
                    $log_message = "Update user {$username} failed";
                    slim_alert('error', $log_message);
                } else {
                    $this->db->transCommit();
                    $log_message = "Update user {$username} success";
                    slim_alert('success', $log_message);
                }

                Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'User', $log_message);
            }
        } else {
            slim_alert('error', 'Update Failed');
        }
        return redirect()->to("sistem/pustakawan");
    }

    function delete()
    {
        $log_message = "Delete failed";
        if ($this->input->isAJAX()) {
            $this->db->transBegin();

            $id         = $this->input->getPost('id');
            $pustakawan = new PustakawanModel();

            $list_pustakawan = $pustakawan->find($id);

            if (!empty($list_pustakawan)) {
                $pustakawan->delete($id);

                if ($this->db->transStatus() === false) {
                    $this->db->transRollback();

                    $log_message = "Delete {$list_pustakawan->username} failed";
                    slim_alert('error', $log_message);

                    $resp = [
                        'status'  => 500,
                        'message' => 'Error'
                    ];
                } else {
                    $this->db->transCommit();
                    $log_message = "Delete {$list_pustakawan->username} success";
                    slim_alert('success', $log_message);

                    $resp = [
                        'status'  => 200,
                        'message' => 'Success'
                    ];
                }
            } else {
                slim_alert('error', $log_message);
                $resp = [
                    'status'  => 410,
                    'message' => 'The requested resource is gone and won’t be coming back'
                ];
            }
        } else {
            $resp = [
                'status'  => 405,
                'message' => 'Method Not Allowed'
            ];
        }

        Services::writeLog(getTypeUser($this->session->user_type), $this->session->user_id, 'User', $log_message);

        return $this->respond($resp);
    }
    private function _storeUserImage(): ?string
    {
        $uploader = new SecureUpload();
        $name     = $uploader->storeImage($this->request->getFile('image'), FCPATH . 'uploads/images/persons', 'user', 1024);
        if ($uploader->getError() !== null) {
            slim_alert('error', 'Foto ditolak: ' . $uploader->getError());
        }

        return $name;
    }
}

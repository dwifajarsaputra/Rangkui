<?php

namespace App\Modules\Membership\Controllers;

use App\Modules\Membership\Models\MembershipModel;
use App\Controllers\BaseController;
use App\Libraries\SecureUpload;
use datetime;

class KeanggotaanController extends BaseController
{

    public $viewPath = "Keanggotaan/";
    private $datetime;
    public $options = [];
    public function __construct()
    {
        $this->datetime = date('YmdHis');
        $this->options = [
            'cost' => 12,
        ];
    }

    public function index()
    {
        $db       = \Config\Database::connect();
        $view     =  $this->viewPath . "v_index";
        $title    = "Membership";
        $subquery = $db->table('mst_member_type')->select('member_type_id, member_type_name')->getCompiledSelect();
        $builder  = $db->table('member as m');
        $builder->select('m.*, mt.member_type_name');
        $builder->join("($subquery) as mt", 'mt.member_type_id = m.member_type_id');
        $builder->where('m.expire_date >=', date('Y-m-d'));

        $content['data'] = $builder->get()->getResult();
        _render($view, $title, $content);
    }


    public function add()
    {
        $membership = new MembershipModel();
        $view       = $this->viewPath . "v_add";
        $title      = 'Membership';
        $content    = ['mst_member_type' => $membership->getMstMemberType()];
        $js         = [
            'assets/custom/js/modules/membership/membership',
        ];

        _render($view, $title, $content, $js);
    }
    public function edit()
    {
        $membership = new MembershipModel();
        $db         = \Config\Database::connect();
        $subquery   = $db->table('mst_member_type')->select('member_type_id, member_type_name')->getCompiledSelect();
        $builder    = $db->table('member as m');
        $view       = $this->viewPath . "v_edit";
        $title      = 'Membership';
        $builder->select('m.*, mt.member_type_name');
        $builder->join("($subquery) as mt", 'mt.member_type_id = m.member_type_id');
        $builder->where('m.member_id =', $this->input->getGet('mi'));

        $content['mst_data'] = $membership->getMstMemberType();
        $content['data']     = $builder->get()->getRow();

        if ($content['data']) {
            unset($content['data']->mpasswd);
        }

        $js               = [
            'assets/custom/js/modules/membership/membership',
        ];

        _render($view, $title, $content, $js);
    }
    /**
     * Field formulir anggota yang boleh disimpan (whitelist; menggantikan extract($_POST)).
     */
    private const MEMBER_FIELDS = [
        'member_id', 'member_name', 'birth_date', 'inst_name', 'member_since_date',
        'member_type_id', 'register_date', 'pin', 'expire_date', 'postal_code', 'gender',
        'member_address', 'member_mail_address', 'member_phone', 'member_fax',
        'member_notes', 'member_email',
    ];

    private function _memberData(): array
    {
        $data = [];
        foreach (self::MEMBER_FIELDS as $field) {
            $value        = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : null;
        }
        $data['is_pending'] = empty($this->request->getPost('is_pending')) ? 0 : 1;
        $data['gender']     = (int) ($data['gender'] ?? 0);

        // Kolom tanggal kosong disimpan sebagai NULL agar tidak memicu error strict mode MySQL.
        foreach (['birth_date', 'member_since_date', 'register_date', 'expire_date'] as $dateField) {
            if ($data[$dateField] === '') {
                $data[$dateField] = null;
            }
        }

        return $data;
    }

    private function _memberRules(): array
    {
        $rules = [
            'member_id'      => 'required|max_length[20]',
            'member_name'    => 'required|max_length[100]',
            'member_type_id' => 'required|is_natural_no_zero',
            'member_email'   => 'permit_empty|valid_email',
            'expire_date'    => 'permit_empty|valid_date[Y-m-d]',
            'mPasswd'        => 'permit_empty|min_length[6]',
        ];
        // Konfirmasi password hanya diperiksa bila password diisi.
        if ((string) $this->request->getPost('mPasswd') !== '') {
            $rules['memberPasswd2'] = 'matches[mPasswd]';
        }

        return $rules;
    }

    private function _storeMemberImage(): ?string
    {
        $uploader = new SecureUpload();
        $image    = $uploader->storeImage($this->request->getFile('member_image'), FCPATH . 'uploads/images/persons', 'member', 1024);
        if ($uploader->getError() !== null) {
            slim_alert('error', $uploader->getError());
        }

        return $image;
    }

    public function save()
    {
        if (! $this->validate($this->_memberRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->_memberData();

        // Perpanjang otomatis 1 tahun bila dicentang, atau bila tanggal kedaluwarsa tidak diisi.
        if (! empty($this->request->getPost('extend')) || empty($data['expire_date'])) {
            $data['expire_date'] = (new \DateTime('+1 year'))->format('Y-m-d');
        }

        $password = (string) $this->request->getPost('mPasswd');
        if ($password !== '') {
            $data['mpasswd'] = password_hash($password, PASSWORD_BCRYPT, $this->options);
        }

        $image = $this->_storeMemberImage();
        if ($image !== null) {
            $data['member_image'] = $image;
        }

        $member = new MembershipModel();
        if ($member->insertMember($data)) {
            slim_alert('success', 'Berhasil Menambah Membership');
        } else {
            slim_alert('error', 'Gagal Menambah Membership');
        }

        return redirect()->to('membership/');
    }

    public function update()
    {
        $memberM            = new MembershipModel();
        $original_member_id = (string) $this->request->getPost('original_member_id');
        $member             = $memberM->find($original_member_id);

        if (empty($member)) {
            slim_alert('error', 'Anggota tidak ditemukan');

            return redirect()->to('/membership/');
        }

        if (! $this->validate($this->_memberRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data     = $this->_memberData();
        $password = (string) $this->request->getPost('mPasswd');
        if ($password !== '') {
            $data['mpasswd'] = password_hash($password, PASSWORD_BCRYPT, $this->options);
        }

        $image = $this->_storeMemberImage();
        if ($image !== null) {
            $data['member_image'] = $image;
        }

        $result = $this->db->table('member')
            ->where('member_id', $original_member_id)
            ->update($data + ['last_update' => date('Y-m-d H:i:s')]);

        if ($result) {
            slim_alert('success', 'Berhasil Ubah Anggota');
        } else {
            log_message('error', json_encode($this->db->error()));
            slim_alert('error', 'Gagal Ubah Anggota');
        }

        return redirect()->to('/membership/');
    }

    public function expMember()
    {
        $db       = \Config\Database::connect();
        $view     =  $this->viewPath . "v_expMember";
        $title    = "Anggota Kadarluwarsa";
        $subquery = $db->table('mst_member_type')->select('member_type_id, member_type_name')->getCompiledSelect();
        $builder  = $db->table('member as m');
        $builder->select('m.*, mt.member_type_name');
        $builder->join("($subquery) as mt", 'mt.member_type_id = m.member_type_id');
        $builder->where('m.expire_date <=', date('Y-m-d'));
        $content['data'] = $builder->get()->getResult();
        $js = [
            'assets/custom/js/modules/membership/membership',
        ];
        _render($view, $title, $content, $js);
    }

    public function updateExp()
    {
        $exp_date       = (string) $this->request->getPost('exp_date');
        $member_id      = (string) $this->request->getPost('member_id');
        $expirationDate = \DateTime::createFromFormat('Y-m-d', $exp_date);
        $memberM        = new MembershipModel();
        $member         = $memberM->find($member_id);

        if ($expirationDate === false || $expirationDate < new \DateTime('today')) {
            slim_alert('error', 'Tanggal tidak valid.');

            return redirect()->to('membership/xmember')->with('showModal', 'myModal');
        }

        if (empty($member)) {
            slim_alert('error', 'Anggota tidak ditemukan');

            return redirect()->to('membership/xmember');
        }

        $ok = $this->db->table('member')
            ->where('member_id', $member_id)
            ->update(['expire_date' => $exp_date, 'last_update' => date('Y-m-d H:i:s')]);

        $name = is_object($member) ? $member->member_name : ($member['member_name'] ?? $member_id);
        slim_alert($ok ? 'success' : 'error', ($ok ? 'Berhasil Mengubah ' : 'Gagal Mengubah ') . $name);

        return redirect()->to('membership/xmember');
    }

    public function delete()
    {
        $member_id = $this->request->getPost('mi');
        $memberM = new membershipModel();
        $member = $memberM->find($member_id);
        
        if (!empty($member)) {
            $this->db->transBegin();
            $result = $memberM->delete($member_id);
            
            if ($result) {
                $this->db->transCommit();
                slim_alert('success', "Berhasil Menghapus Anggota");
            } else {
                $this->db->transRollback();
                slim_alert('error', "Gagal Menghapus Anggota");
            }
        } else {
            slim_alert('error', "Anggota Tidak Ditemukan");
        }
        
        return redirect()->to('membership/');
    }
}

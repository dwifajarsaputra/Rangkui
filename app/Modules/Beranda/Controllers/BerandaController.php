<?php

namespace App\Modules\Beranda\Controllers;

use SimpleXMLElement;
use CodeIgniter\API\ResponseTrait;
use App\Controllers\BaseController;
use App\Modules\Beranda\Models\BerandaModel;

class BerandaController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $loadModel = new BerandaModel();
        $css       = ["https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css", "assets/custom/css/modules/beranda/beranda"];
        $js        = ["https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js", "assets/custom/js/modules/beranda/beranda"];
        $view      = "v_index";
        $title     = "DIFOSS";
        $data      = $loadModel->biblio(null, 10);
        $content['data'] = $data;
        _renderView($view, $title, $content, $js, $css);
    }
    public function detail($id)
    {
        $id = (int) slim_decrypt($id);

        if ($this->request->getGet('p') === 'show_detail') {
            return $this->_showDetail($id, filter_var($this->request->getGet('inXML'), FILTER_VALIDATE_BOOLEAN));
        } else {
            $loadModel = new BerandaModel();
            $css       = ["assets/custom/css/modules/beranda/beranda"];
            $js        = ["assets/custom/js/modules/beranda/beranda"];
            $view      = "v_detail";
            $title     = "DIFOSS";
            $data      = $loadModel->biblio($id);

            if (!$data) {
                slim_alert('error', 'Bibliography not found');
                return redirect()->to(base_url());
            }
            $data->author      = $loadModel->biblio_author($id);
            $data->supervisor  = $loadModel->biblio_supervisor($id);
            $data->examiner    = $loadModel->biblio_examiner($id);
            $data->contributor = $loadModel->biblio_contributor($id);
            $data->topic       = $loadModel->biblio_topic($id);
            $data->attachment  = $loadModel->biblio_attachment($id);

            $content['data'] = $data;
            _renderView($view, $title, $content, $js, $css);
        }
    }

    function _showDetail($biblio_id, $inXML = false)
    {
        if ($inXML) {
            $model = new BerandaModel();
            $sysConf = config('SysConf');

            $xml = new SimpleXMLElement(
                '<modsCollection xmlns:xlink="http://www.w3.org/1999/xlink" 
                                 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                                 xmlns="http://www.loc.gov/mods/v3" 
                                 xmlns:slims="http://slims.web.id" 
                                 xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-3.xsd" />'
            );

            $modsNode = $xml->addChild('mods');
            $modsNode->addAttribute('version', '3.3');
            $modsNode->addAttribute('ID', $biblio_id);

            $data = $this->_getRecord($biblio_id);

            $data['title'] = str_replace('/', '&#47;', $data['title']);
            $_title_main = $data['title'];
            $_title_sub = '';
            $_title_statement_resp = '';

            if (strpos($data['title'], ':') !== false) {
                list($_title_main, $_title_sub) = array_map('trim', explode(':', $data['title'], 2));
            } elseif (strpos($data['title'], '/') !== false) {
                list($_title_main, $_title_statement_resp) = array_map('trim', explode('/', $data['title'], 2));
            }

            // title info
            $titleInfo = $modsNode->addChild('titleInfo');
            $t = $titleInfo->addChild('title');
            $this->addCData($t, $_title_main);

            if ($_title_sub !== "") {
                $t_sub = $titleInfo->addChild('subTitle');
                $this->addCData($t_sub, $_title_sub);
            }

            // authors
            $authors     = $model->biblio_author($biblio_id);
            if (!empty($authors) || !is_null($authors)) {
                foreach ($authors as $key => $author) {
                    $name = $modsNode->addChild('name');
                    $name->addAttribute('type', $sysConf->authority_type[$author->authority_type]);
                    $name->addAttribute('authority', $author->auth_list);
                    $name->addChild('namePart', $this->_xmlText($author->author_name));

                    // role
                    $role = $name->addChild('role');
                    $role->addAttribute('type', 'text');
                    $role->addChild('roleTerm', $this->_xmlText($sysConf->authority_level[$author->level]));
                }
            }

            // supervisors
            $supervisors  = $model->biblio_supervisor($biblio_id);
            if (!empty($supervisors) || !is_null($supervisors)) {
                foreach ($supervisors as $key => $spv) {
                    $name = $modsNode->addChild('name');
                    $name->addAttribute('type', $sysConf->supervisor_type[$spv->supervisor_type]);
                    $name->addAttribute('authority', $spv->supervisor_list);
                    $name->addChild('namePart', $this->_xmlText($spv->supervisor_name));

                    // role
                    $role = $name->addChild('role');
                    $role->addAttribute('type', 'text');
                    $role->addChild('roleTerm', $this->_xmlText($sysConf->authority_level_supervisor[$spv->level]));
                }
            }

            //examiners
            $examiners    = $model->biblio_examiner($biblio_id);
            if (!empty($examiners) || !is_null($examiners)) {
                foreach ($examiners as $key => $ex) {
                    $name = $modsNode->addChild('name');
                    $name->addAttribute('type', $sysConf->examiner_type[$ex->examiner_type]);
                    $name->addChild('namePart', $this->_xmlText($ex->examiner_name));

                    // role
                    $role = $name->addChild('role');
                    $role->addAttribute('type', 'text');
                    $role->addChild('roleTerm', $this->_xmlText($sysConf->authority_level_examiner[$ex->level]));
                }
            }

            // contributors
            $contributors = $model->biblio_contributor($biblio_id);
            if (!empty($contributors) || !is_null($contributors)) {
                foreach ($contributors as $key => $contributor) {
                    $name = $modsNode->addChild('name');
                    $name->addAttribute('type', $sysConf->contributor_type[$contributor->contributor_type]);
                    $name->addChild('namePart', $this->_xmlText($contributor->contributor_name));

                    // role
                    $role = $name->addChild('role');
                    $role->addAttribute('type', 'text');
                    $role->addChild('roleTerm', $this->_xmlText($sysConf->authority_level_contributor[$contributor->level]));
                }
            }

            // $modsNode->addChild('< manuscript="yes" collection="yes" />', $this->_xmlText('mixed material'));
            $typeResource = $modsNode->addChild('typeOfResource', $this->_xmlText("mixed material"));
            $typeResource->addAttribute("manuscript", "yes");
            $typeResource->addAttribute("collection", "yes");

            $marcgt = $modsNode->addChild("genre", $this->_xmlText("bibliography"));
            $marcgt->addAttribute("authority", "marcgt");

            // imprint/publication data
            $originInfo = $modsNode->addChild('originInfo');

            $place = $originInfo->addChild('place');
            $place->addChild('placeTerm', $this->_xmlText($data['publish_place']));

            $originInfo->addChild('publisher', $this->_xmlText($data['publisher_name']));
            $originInfo->addChild('dateIssued', $this->_xmlText($data['publish_year']));

            $freq = (int) $data['frequency'];
            if ($freq > 0) {
                $originInfo->addChild('dateIssued', $this->_xmlText($data['frequency']));
            } else {
                $originInfo->addChild('dateIssued', $this->_xmlText('monographic'));
            }

            $originInfo->addChild('edition', $this->_xmlText($data['edition']));

            // language
            $lang = $modsNode->addChild('language');
            $lang->addChild('languageTerm', $this->_xmlText($data['language_id']));
            $lang->addChild('languageTerm', $this->_xmlText($data['language_name']));

            // item type
            $item = $modsNode->addChild('itemType');

            $code = $item->addChild('itemTypeTerm', $this->_xmlText($data['item_type_code']));
            $code->addAttribute('type', 'code');

            $text = $item->addChild('itemTypeTerm', $this->_xmlText($data['item_type_name']));
            $text->addAttribute('type', 'text');

            //copyright
            $copy = $modsNode->addChild('copyright');
            $code = $copy->addChild('copyrightTerm', $this->_xmlText($data['copyright_id']));
            $code->addAttribute('type', 'code');

            $text = $copy->addChild('copyrightTerm', $this->_xmlText($data['copyright_name']));
            $text->addAttribute('type', 'text');

            // Physical Description/Collation
            $physical = $modsNode->addChild('physicalDescription');
            $form = $physical->addChild('form', $this->_xmlText($data['gmd_name']));
            $form->addAttribute('authority', 'gmd');

            $exten = $physical->addChild('extent', $this->_xmlText($data['collation']));

            if (!is_null($data['series_title']) && $data['series_title'] !== "") {

                $relatedItem = $modsNode->addChild('relatedItem');
                $relatedItem->addAttribute('type', 'series');

                $titleInfo = $relatedItem->addChild('titleInfo');
                $titleInfo->addChild('title', $this->_xmlText($data['series_title']));
            }

            // note
            $note = $modsNode->addChild('note', $this->_xmlText($data['notes']));
            if ($_title_statement_resp != "") {
                $nc = $note->addChild('note');
                $this->addCData($nc, $_title_statement_resp);

                $nc->addAttribute('type', 'statement of responsibility');
            }

            $topics = $model->biblio_topic($biblio_id);

            if (!empty($topics) || !is_null($topics)) {
                foreach ($topics as $key => $topic) {
                    $subject_type = strtolower($sysConf->subject_type[$topic->topic_type]);
                    $sub = $modsNode->addChild('subject');

                    $sub->addAttribute('authority', $topic->auth_list);
                    $sub->addChild($subject_type, $this->_xmlText($topic->topic));
                }
            }

            // classification
            $modsNode->addChild('classification', $this->_xmlText($data['classification']));
            $modsNode->addChild('ministry', $this->_xmlText($data['code_ministry']));
            $modsNode->addChild('studentID', $this->_xmlText($data['student_id']));

            $identifier = $modsNode->addChild('identifier', $this->_xmlText(str_replace(array('-', ' '), '', $data['isbn_issn'])));
            $identifier->addAttribute('type', 'isbn');

            $modsNode->addChild('departementID', $this->_xmlText($data['departement']));
            $modsNode->addChild('urlCrossref', $this->_xmlText($data['url_crossref']));


            $loc = $modsNode->addChild('location');
            $loc->addChild('physicalLocation', $this->_xmlText($sysConf->library['name'] . ' ' . $sysConf->library['subname']));
            $loc->addChild('shelfLocator', $this->_xmlText($data['call_number']));



            $location = $model->biblio_location($biblio_id);

            if (!empty($location) || !is_null($location)) {
                $list = $location;

                $hold = $loc->addChild('holdingSimple');
                foreach ($list as $key => $val) {
                    $cpi = $hold->addChild('copyInformation');

                    $num = $cpi->addChild('numerationAndChronology', $this->_xmlText($val['item_code']));
                    $num->addAttribute('type', '1');

                    $cpi->addChild('sublocation', $this->_xmlText($val['location_name'] . ($val['site'] ? ' (' . $val['site'] . ')' : '')));
                    $cpi->addChild('shelfLocator', $this->_xmlText($val['call_number']));
                }
            }



            $attachment = $model->biblio_attachment($biblio_id);
            if (!empty($attachment) || !is_null($attachment)) {
                $slims = $modsNode->addChild('slims:digitals');

                foreach ($attachment as $key => $val) {
                    if ($val->access_limit) {
                        continue;
                    }

                    $sdi = $slims->addChild('slims:digital_item');
                    $sdi->addAttribute('id', $val->file_id);
                    $sdi->addAttribute('url', trim($val->file_url));
                    $sdi->addAttribute('path', htmlentities($val->file_dir . '/' . $val->file_name));
                    $sdi->addAttribute('mimetype', $val->mime_type);
                }
            }


            if (!empty($data['image'])) {
                $image = urlencode($data['image']);

                $sim = $modsNode->addChild('slims:image');
                $this->addCData($sim, $image);
            }

            // Create a new XML element for 'recordInfo'
            $recordInfo = $modsNode->addChild('recordInfo');

            $recordInfo->addChild('recordIdentifier');
            $this->addCData($recordInfo, $biblio_id);

            $recordCreationDate = $recordInfo->addChild('recordCreationDate');
            $recordCreationDate->addAttribute('encoding', 'w3cdtf');
            $this->addCData($recordCreationDate, $data['input_date']);

            $recordChangeDate = $recordInfo->addChild('recordChangeDate');
            $recordChangeDate->addAttribute('encoding', 'w3cdtf');
            $this->addCData($recordChangeDate, $data['last_update']);

            $recordInfo->addChild('recordOrigin', $this->_xmlText('machine generated'));



            return $this->respondXML($xml);
        }
    }

    // Function to send XML response
    /**
     * SimpleXMLElement::addChild() tidak meng-escape "&" sehingga nama seperti "A & B" membuat XML gagal dibuat.
     */
    private function _xmlText($value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function respondXML(SimpleXMLElement $xml)
    {
        return $this->response
            ->setHeader('Content-Type', 'application/xml')
            ->setBody($xml->asXML());
    }

    function addCData(SimpleXMLElement $node, $cdata_text)
    {
        // Import the SimpleXMLElement into DOM
        $dom = dom_import_simplexml($node);
        // Get the owner document (DOMDocument) of the node
        $owner = $dom->ownerDocument;
        // Create a CDATA section with the provided text
        $dom->appendChild($owner->createCDATASection($cdata_text));
    }


    function _getRecord($biblio_id)
    {
        $sql = "SELECT
                    b.*,
                    l.language_name,
                    p.publisher_name,
                    mc.copyright_id,
                    mc.copyright_name,
                    mit.item_type_name ,
                    mit.item_type_code,
                    pl.place_name AS publish_place,
                    gmd.gmd_name,
                    fr.frequency
                FROM
                    biblio AS b
                LEFT JOIN mst_gmd AS gmd ON
                    b.gmd_id = gmd.gmd_id
                LEFT JOIN mst_language AS l ON
                    b.language_id = l.language_id
                LEFT JOIN mst_publisher AS p ON
                    b.publisher_id = p.publisher_id
                LEFT JOIN mst_place AS pl ON
                    b.publish_place_id = pl.place_id
                LEFT JOIN mst_frequency AS fr ON
                    b.frequency_id = fr.frequency_id
                LEFT JOIN mst_copyright AS mc ON
                    b.copyright_id = mc.copyright_id
                LEFT JOIN mst_item_type AS mit ON
                    b.item_type_id = mit.item_type_id
                WHERE
                    biblio_id = :biblio_id:";

        $record_detail = $this->db->query($sql, ['biblio_id' => $biblio_id])->getRowArray();

        return $record_detail;
    }
    public function search()
    {
        $loadModel = new BerandaModel();
        $css       = ["assets/custom/css/modules/beranda/beranda"];
        $js        = [];
        $view      = "v_search";
        $title     = "DIFOSS";
        $query     = $this->request->getGet();

        if (isset($query['s'])) {
            $data = $loadModel->search_biblio(is_string($query['s']) ? $query['s'] : '');
        } else {
            // Pencarian spesifik: ?<kolom>=<kata kunci>, kolom divalidasi dengan whitelist.
            $data = [];
            foreach ($query as $column => $value) {
                if (is_string($value) && $loadModel->isSearchableColumn((string) $column)) {
                    $data = $loadModel->search_biblio_specific((string) $column, $value);
                    break;
                }
            }
        }

        $content['data'] = $data;
        _renderView($view, $title, $content, $js, $css);
    }

    /**
     * Unduh/lihat lampiran: memeriksa hak akses, mencatat statistik unduhan, lalu
     * menyajikan berkas dari penyimpanan (berkas tidak lagi diakses langsung via URL publik).
     */
    public function file($biblioId, $fileId)
    {
        $biblioId = (int) $biblioId;
        $fileId   = (int) $fileId;
        $isStaff  = $this->session->has('user_id');

        $row = $this->db->table('biblio_attachment ba')
            ->select('ba.access_type, ba.access_limit, f.file_name, f.file_url, f.mime_type, f.file_title, b.opac_hide')
            ->join('files f', 'f.file_id = ba.file_id')
            ->join('biblio b', 'b.biblio_id = ba.biblio_id')
            ->where('ba.biblio_id', $biblioId)
            ->where('ba.file_id', $fileId)
            ->get()
            ->getRow();

        if ($row === null || (! $isStaff && (int) $row->opac_hide === 1)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (! $isStaff) {
            if ($row->access_type !== 'public') {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            if (! empty($row->access_limit)) {
                slim_alert('warning', 'Silakan login untuk mengakses lampiran ini.');

                return redirect()->to('login');
            }
            $this->db->table('biblio_count')->insert(['biblio_id' => $biblioId, 'file_id' => $fileId]);
        }

        $name = basename((string) $row->file_name);
        foreach ([WRITEPATH . 'uploads/repository/', FCPATH . 'uploads/repository/'] as $dir) {
            $path = $dir . $name;
            if ($name !== '' && is_file($path)) {
                $download = $this->response->download($path, null)->setFileName($name);

                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf') {
                    return $download->setContentType('application/pdf')->inline();
                }

                return $download;
            }
        }

        // Lampiran berupa tautan eksternal.
        if (preg_match('#^https?://#i', (string) $row->file_url)) {
            return redirect()->to($row->file_url);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }
}

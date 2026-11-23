<?php

namespace App\Modules\Beranda\Models;

use MongoDB\Client;
use Config\Services;
use App\Models\BaseModel;

use function PHPSTORM_META\map;
use MongoDB\Exception\Exception;

class BerandaModel extends BaseModel
{


    public function biblio($biblio_id = null, $limit = null)
    {
        $binds = [];
        // Koleksi yang disembunyikan dari OPAC tidak boleh tampil di halaman publik.
        $where = ' WHERE (b.opac_hide IS NULL OR b.opac_hide = 0) ';
        $lm    = '';
        if (!is_null($biblio_id)) {
            $where  .= ' AND b.biblio_id = ? ';
            $binds[] = (int) $biblio_id;
            $result  = false;
        } else {
            $result = [];
        }
        if (!is_null($limit)) {
            $lm = ' LIMIT ' . max(1, (int) $limit) . ' ';
        }
        $sql = "SELECT
                    b.*,
                    (SELECT mg.gmd_name FROM mst_gmd mg WHERE mg.gmd_id = b.gmd_id LIMIT 1) gmd,
                    (SELECT ml.language_name FROM mst_language ml WHERE ml.language_id = b.language_id LIMIT 1) language,
                    (SELECT mcm.name_prodi FROM mst_code_ministry mcm WHERE mcm.code_ministry = b.code_ministry LIMIT 1) ministry,
                    (SELECT mp.publisher_name FROM mst_publisher mp WHERE mp.publisher_id = b.publisher_id LIMIT 1) publisher,
                    (SELECT mp.place_name FROM mst_place mp WHERE mp.place_id = b.publish_place_id LIMIT 1) publisher_place,
                    (SELECT ml.license_name FROM mst_license ml WHERE ml.license_id = b.license_id LIMIT 1) licence,
                    (SELECT mc.copyright_name FROM mst_copyright mc WHERE mc.copyright_id = b.copyright_id LIMIT 1) copyright,
                    (SELECT mit.item_type_name FROM mst_item_type mit WHERE mit.item_type_id = b.item_type_id LIMIT 1) item_type
                FROM
                    biblio b 
                 $where ORDER BY b.biblio_id DESC 
                 $lm;";
        $query  = $this->db->query($sql, $binds);
        if ($query->getNumRows() > 0) {
            if (!is_null($biblio_id)) {
                $result = $query->getRow();
            } else {
                $result = $query->getResult();
            }
        }
        return $result;
    }
    function biblio_author($biblio_id)
    {
        $sql = "SELECT
                ba.`level` ,
                ma.*
            FROM
                biblio_author ba
            JOIN mst_author ma ON
                ma.author_id = ba.author_id
            WHERE
                ba.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }

        return $result;
    }
    function biblio_supervisor($biblio_id)
    {
        $sql = "SELECT
                    bs.`level` ,
                    ms.*
                FROM
                    biblio_supervisor bs
                JOIN mst_supervisor ms ON
                    bs.supervisor_id = ms.supervisor_id
                WHERE
                    bs.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }
        return $result;
    }
    function biblio_examiner($biblio_id)
    {
        $sql = "SELECT
                    be.`level`,
                    me.*
                FROM
                    biblio_examiner be
                JOIN mst_examiner me ON
                    be.examiner_id = me.examiner_id
                WHERE
                    be.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }
        return $result;
    }
    function biblio_contributor($biblio_id)
    {
        $sql = "SELECT
                    bc.`level`,
                    mc.*
                FROM
                    biblio_contributor bc 
                JOIN mst_contributor mc ON
                    bc.contributor_id = mc.contributor_id 
                WHERE
                    bc.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }
        return $result;
    }
    function biblio_topic($biblio_id)
    {
        $sql = "SELECT 
                    bt.`level`,
                    mt.* 
                FROM biblio_topic bt
                JOIN mst_topic mt ON
                    bt.topic_id = mt.topic_id 
                WHERE
                    bt.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }
        return $result;
    }
    function biblio_attachment($biblio_id)
    {
        $sql = "SELECT
                    ba.access_type,
                    ba.access_limit,
                    f.*
                FROM
                    biblio_attachment ba
                JOIN files f ON
                    f.file_id = ba.file_id
                WHERE
                    ba.biblio_id = ?";
        $query  = $this->db->query($sql, [(int) $biblio_id]);
        $result = [];
        if ($query->getNumRows() > 0) {
            $result = $query->getResult();
        }
        return $result;
    }

    function biblio_location($biblio_id)
    {
        $sql = "SELECT 
                    i.item_code, 
                    i.call_number, 
                    stat.item_status_name, 
                    loc.location_name, 
                    stat.rules, 
                    i.site 
                FROM 
                    item AS i
                LEFT JOIN 
                    mst_item_status AS stat ON i.item_status_id = stat.item_status_id
                LEFT JOIN 
                    mst_location AS loc ON i.location_id = loc.location_id
                WHERE 
                    i.biblio_id = :biblio_id:";
        $query = $this->db->query($sql, ['biblio_id' => $biblio_id]);
        $result = [];

        if ($query->getNumRows() > 0) {
            $result = $query->getResultArray();
        }

        return $result;
    }
    /**
     * Kolom search_biblio yang boleh dipakai untuk pencarian spesifik (whitelist).
     */
    public const SEARCHABLE_COLUMNS = [
        'title', 'author', 'topic', 'publisher', 'publish_year', 'isbn_issn',
        'supervisor', 'examiner', 'contributor', 'call_number', 'classification',
        'gmd', 'location', 'collection_types', 'language', 'notes',
    ];

    private function _searchColumns(): array
    {
        static $cols = null;
        if ($cols === null) {
            $existing = $this->db->getFieldNames('search_biblio');
            $cols     = array_values(array_intersect(self::SEARCHABLE_COLUMNS, $existing));
        }

        return $cols;
    }

    /**
     * Apakah kolom ini boleh dicari?
     */
    public function isSearchableColumn(?string $column): bool
    {
        return $column !== null && in_array($column, $this->_searchColumns(), true);
    }

    private function _hiddenFilter($builder)
    {
        // search_biblio tidak selalu punya kolom opac_hide; filter lewat tabel biblio.
        return $builder->whereNotIn('biblio_id', static function ($sub) {
            return $sub->select('biblio_id')->from('biblio')->where('opac_hide', 1);
        });
    }

    private function _mongoSearch(array $columns, string $get): array
    {
        if (! class_exists(\MongoDB\Client::class)) {
            return [];
        }
        try {
            $collection = Services::mongo()->biblio;
            // Escape regex agar input pengguna diperlakukan sebagai teks biasa (mencegah ReDoS/injeksi).
            $regex = new \MongoDB\BSON\Regex(preg_quote($get, '/'), 'i');
            $or    = [];
            foreach ($columns as $column) {
                $or[] = [$column => $regex];
            }
            $result_array = $collection->find(['$or' => $or], ['limit' => 500])->toArray();

            return json_decode(json_encode($result_array), false) ?: [];
        } catch (\Throwable $e) {
            log_message('error', 'Mongo search failed: ' . $e->getMessage());

            return [];
        }
    }

    function search_biblio($get)
    {
        $get = mb_substr(trim((string) $get), 0, 200);
        if ($get === '') {
            return [];
        }

        if (env('INDEX_TYPE', 'mysql') !== 'mysql') {
            return $this->_mongoSearch(['title', 'author', 'topic'], $get);
        }

        $builder = $this->db->table('search_biblio')
            ->groupStart()
            ->like('title', $get)
            ->orLike('author', $get)
            ->orLike('topic', $get)
            ->groupEnd();

        return $this->_hiddenFilter($builder)
            ->orderBy('biblio_id', 'DESC')
            ->limit(500)
            ->get()
            ->getResult();
    }

    function search_biblio_specific($column, $get)
    {
        $get = mb_substr(trim(rawurldecode((string) $get)), 0, 200);
        if ($get === '' || ! $this->isSearchableColumn($column)) {
            return [];
        }

        if (env('INDEX_TYPE', 'mysql') !== 'mysql') {
            return $this->_mongoSearch([$column], $get);
        }

        $builder = $this->db->table('search_biblio')->like($column, $get);

        return $this->_hiddenFilter($builder)
            ->orderBy('biblio_id', 'DESC')
            ->limit(500)
            ->get()
            ->getResult();
    }
}

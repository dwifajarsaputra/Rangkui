<?php

namespace App\Libraries\OaiPmh;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Akses data untuk endpoint OAI-PMH. Semua tanggal dikembalikan dalam UTC
 * (granularitas YYYY-MM-DDThh:mm:ssZ) sesuai spesifikasi OAI-PMH 2.0.
 */
class OaiRepository
{
    /** Pemetaan kode jenis karya Rangkui → kosakata info:eu-repo (OpenAIRE Guidelines). */
    public const EU_REPO_TYPES = [
        'BS'  => 'info:eu-repo/semantics/bachelorThesis',
        'THS' => 'info:eu-repo/semantics/masterThesis',
        'DSR' => 'info:eu-repo/semantics/doctoralThesis',
    ];

    private BaseConnection $db;
    private DateTimeZone $localTz;
    private DateTimeZone $utc;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db      = $db ?? \Config\Database::connect();
        $this->localTz = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $this->utc     = new DateTimeZone('UTC');
    }

    public function repositoryName(): string
    {
        $name = $this->_setting('library_name');
        $sub  = $this->_setting('library_subname');

        return trim(($name ?: 'Rangkui Repository') . ($sub ? ' - ' . $sub : ''));
    }

    public function adminEmail(): string
    {
        $configured = trim((string) env('oai.adminEmail', ''));
        if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        $row = $this->db->table('user')->select('email')->where('user_type', 1)->where('email !=', '')->orderBy('user_id')->get(1)->getRow();
        if ($row !== null && filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            return $row->email;
        }

        return 'halo@setiadifoss.org';
    }

    public function earliestDatestamp(): string
    {
        $row = $this->_visible($this->db->table('biblio b'))->selectMin('b.last_update', 'm')->get()->getRow();

        return $this->toUtc($row->m ?? null) ?? '1970-01-01T00:00:00Z';
    }

    /**
     * Daftar set: jenis karya (type:<kode>) dan program studi (prodi:<kode>).
     *
     * @return list<array{spec:string,name:string}>
     */
    public function sets(): array
    {
        $sets = [['spec' => 'type', 'name' => 'Jenis Karya'], ['spec' => 'prodi', 'name' => 'Program Studi']];

        foreach ($this->db->table('mst_item_type')->orderBy('item_type_id')->get()->getResult() as $t) {
            $sets[] = ['spec' => 'type:' . $this->setToken((string) ($t->item_type_code ?: $t->item_type_id)), 'name' => (string) $t->item_type_name];
        }
        foreach ($this->db->table('mst_code_ministry')->orderBy('code_ministry')->get()->getResult() as $p) {
            $sets[] = ['spec' => 'prodi:' . $this->setToken((string) $p->code_ministry), 'name' => trim($p->name_prodi . ($p->degree ? ' (' . $p->degree . ')' : ''))];
        }

        return $sets;
    }

    public function setExists(string $spec): bool
    {
        foreach ($this->sets() as $set) {
            if ($set['spec'] === $spec) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ambil satu halaman rekaman dengan cursor berbasis biblio_id.
     *
     * @return array{rows:list<object>,total:int,hasMore:bool}
     */
    public function page(?string $fromUtc, ?string $untilUtc, ?string $set, int $afterId, int $limit): array
    {
        $count = $this->_filtered($fromUtc, $untilUtc, $set)->countAllResults();

        $rows = $this->_filtered($fromUtc, $untilUtc, $set)
            ->select('b.*')
            ->where('b.biblio_id >', $afterId)
            ->orderBy('b.biblio_id', 'ASC')
            ->limit($limit + 1)
            ->get()
            ->getResult();

        $hasMore = count($rows) > $limit;

        return ['rows' => array_slice($rows, 0, $limit), 'total' => $count, 'hasMore' => $hasMore];
    }

    public function find(int $biblioId): ?object
    {
        return $this->_visible($this->db->table('biblio b'))->select('b.*')->where('b.biblio_id', $biblioId)->get()->getRow();
    }

    /**
     * Lengkapi rekaman dengan data relasi (penulis, subjek, pembimbing, dsb.) secara batch.
     *
     * @param list<object> $rows
     *
     * @return list<object>
     */
    public function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn ($r) => (int) $r->biblio_id, $rows);

        $people = static function (BaseConnection $db, string $link, string $master, string $key, string $name) use ($ids): array {
            $out = [];
            $res = $db->table($link . ' l')
                ->select("l.biblio_id, l.level, m.{$name} AS name")
                ->join($master . ' m', "m.{$key} = l.{$key}")
                ->whereIn('l.biblio_id', $ids)
                ->orderBy('l.level')
                ->get()->getResult();
            foreach ($res as $r) {
                $out[(int) $r->biblio_id][] = $r;
            }

            return $out;
        };

        $authors      = $people($this->db, 'biblio_author', 'mst_author', 'author_id', 'author_name');
        $topics       = $people($this->db, 'biblio_topic', 'mst_topic', 'topic_id', 'topic');
        $supervisors  = $people($this->db, 'biblio_supervisor', 'mst_supervisor', 'supervisor_id', 'supervisor_name');
        $examiners    = $this->_tableExists('biblio_examiner') ? $people($this->db, 'biblio_examiner', 'mst_examiner', 'examiner_id', 'examiner_name') : [];
        $contributors = $this->_tableExists('biblio_contributor') ? $people($this->db, 'biblio_contributor', 'mst_contributor', 'contributor_id', 'contributor_name') : [];

        $files = [];
        $res   = $this->db->table('biblio_attachment ba')
            ->select('ba.biblio_id, ba.file_id, ba.access_type, ba.access_limit, f.mime_type, f.file_name')
            ->join('files f', 'f.file_id = ba.file_id')
            ->whereIn('ba.biblio_id', $ids)
            ->get()->getResult();
        foreach ($res as $r) {
            $files[(int) $r->biblio_id][] = $r;
        }

        $lookup = function (string $table, string $key): array {
            $map = [];
            foreach ($this->db->table($table)->get()->getResult() as $r) {
                $map[(string) $r->{$key}] = $r;
            }

            return $map;
        };
        $itemTypes  = $lookup('mst_item_type', 'item_type_id');
        $prodi      = $lookup('mst_code_ministry', 'code_ministry');
        $publishers = $lookup('mst_publisher', 'publisher_id');
        $languages  = $lookup('mst_language', 'language_id');
        $licenses   = $lookup('mst_license', 'license_id');
        $copyrights = $lookup('mst_copyright', 'copyright_id');

        foreach ($rows as $r) {
            $id               = (int) $r->biblio_id;
            $r->authors       = array_column($authors[$id] ?? [], 'name');
            $r->topics        = array_column($topics[$id] ?? [], 'name');
            $r->supervisors   = array_column($supervisors[$id] ?? [], 'name');
            $r->examiners     = array_column($examiners[$id] ?? [], 'name');
            $r->contributors  = array_column($contributors[$id] ?? [], 'name');
            $r->files         = $files[$id] ?? [];
            $r->itemType      = $itemTypes[(string) $r->item_type_id] ?? null;
            $r->prodi         = $prodi[(string) $r->code_ministry] ?? null;
            $r->publisherName = $publishers[(string) $r->publisher_id]->publisher_name ?? null;
            $r->languageName  = $languages[(string) $r->language_id]->language_name ?? null;
            $r->licenseName   = $licenses[(string) $r->license_id]->license_name ?? null;
            $r->copyrightName = $copyrights[(string) $r->copyright_id]->copyright_name ?? null;
        }

        return $rows;
    }

    /**
     * Kode setSpec jenis karya untuk item_type_id (di-cache per request).
     */
    public function itemTypeToken($itemTypeId): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach ($this->db->table('mst_item_type')->get()->getResult() as $t) {
                $map[(string) $t->item_type_id] = $this->setToken((string) ($t->item_type_code ?: $t->item_type_id));
            }
        }

        return $map[(string) $itemTypeId] ?? null;
    }

    public function toUtc(?string $local): ?string
    {
        if ($local === null || $local === '' || str_starts_with($local, '0000')) {
            return null;
        }
        try {
            return (new DateTimeImmutable($local, $this->localTz))->setTimezone($this->utc)->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function utcToLocal(string $utc): string
    {
        return (new DateTimeImmutable($utc, $this->utc))->setTimezone($this->localTz)->format('Y-m-d H:i:s');
    }

    /**
     * setSpec hanya boleh berisi karakter "unreserved" URI.
     */
    public function setToken(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9\-_.!~*\'()]/', '_', $value);
    }

    // ------------------------------------------------------------------

    private function _visible($builder)
    {
        return $builder->groupStart()->where('b.opac_hide', 0)->orWhere('b.opac_hide IS NULL', null, false)->groupEnd();
    }

    private function _filtered(?string $fromUtc, ?string $untilUtc, ?string $set)
    {
        $b = $this->_visible($this->db->table('biblio b'));
        if ($fromUtc !== null) {
            $b->where('b.last_update >=', $this->utcToLocal($fromUtc));
        }
        if ($untilUtc !== null) {
            $b->where('b.last_update <=', $this->utcToLocal($untilUtc));
        }
        if ($set !== null && $set !== 'type' && $set !== 'prodi') {
            [$kind, $value] = explode(':', $set, 2) + [1 => ''];
            if ($kind === 'type') {
                $ids = [];
                foreach ($this->db->table('mst_item_type')->get()->getResult() as $t) {
                    if ($this->setToken((string) ($t->item_type_code ?: $t->item_type_id)) === $value) {
                        $ids[] = (int) $t->item_type_id;
                    }
                }
                $b->whereIn('b.item_type_id', $ids ?: [-1]);
            } elseif ($kind === 'prodi') {
                $codes = [];
                foreach ($this->db->table('mst_code_ministry')->select('code_ministry')->get()->getResult() as $p) {
                    if ($this->setToken((string) $p->code_ministry) === $value) {
                        $codes[] = (string) $p->code_ministry;
                    }
                }
                $b->whereIn('b.code_ministry', $codes ?: ['__none__']);
            }
        } elseif ($set === 'type') {
            $b->where('b.item_type_id IS NOT NULL', null, false);
        } elseif ($set === 'prodi') {
            $b->where("b.code_ministry IS NOT NULL AND b.code_ministry != ''", null, false);
        }

        return $b;
    }

    private function _setting(string $name): ?string
    {
        $row = $this->db->table('setting')->select('setting_value')->where('setting_name', $name)->get()->getRow();
        if ($row === null) {
            return null;
        }
        $value = @unserialize((string) $row->setting_value, ['allowed_classes' => ['stdClass']]);

        return is_string($value) ? $value : null;
    }

    private function _tableExists(string $table): bool
    {
        static $tables = null;
        $tables ??= $this->db->listTables();

        return in_array($table, $tables, true);
    }
}

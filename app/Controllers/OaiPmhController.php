<?php

namespace App\Controllers;

use App\Libraries\OaiPmh\OaiRepository;
use CodeIgniter\HTTP\ResponseInterface;
use XMLWriter;

/**
 * Endpoint OAI-PMH 2.0 — https://www.openarchives.org/OAI/openarchivesprotocol.html
 *
 * Verb: Identify, ListMetadataFormats, ListSets, ListIdentifiers, ListRecords, GetRecord.
 * Format metadata: oai_dc (Dublin Core), diperkaya dengan kosakata info:eu-repo agar ramah
 * OpenAIRE/BASE/Garuda. Pagination memakai resumptionToken tanpa state di server.
 *
 * Konfigurasi opsional di .env:
 *   oai.repositoryIdentifier = repo.kampus.ac.id   (default: host dari app.baseURL)
 *   oai.adminEmail           = repositori@kampus.ac.id
 *   oai.pageSize             = 100
 */
class OaiPmhController extends BaseController
{
    private const VERBS = [
        'Identify'            => ['required' => [], 'optional' => [], 'exclusive' => null],
        'ListMetadataFormats' => ['required' => [], 'optional' => ['identifier'], 'exclusive' => null],
        'ListSets'            => ['required' => [], 'optional' => [], 'exclusive' => 'resumptionToken'],
        'ListIdentifiers'     => ['required' => ['metadataPrefix'], 'optional' => ['from', 'until', 'set'], 'exclusive' => 'resumptionToken'],
        'ListRecords'         => ['required' => ['metadataPrefix'], 'optional' => ['from', 'until', 'set'], 'exclusive' => 'resumptionToken'],
        'GetRecord'           => ['required' => ['identifier', 'metadataPrefix'], 'optional' => [], 'exclusive' => null],
    ];

    private const FORMATS = [
        'oai_dc' => [
            'schema'    => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
            'namespace' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        ],
    ];

    private OaiRepository $repo;
    private XMLWriter $xml;
    private string $repoId;
    private int $pageSize;

    public function index(): ResponseInterface
    {
        $this->repo     = new OaiRepository($this->db);
        $this->repoId   = $this->_repositoryIdentifier();
        $this->pageSize = max(10, min(1000, (int) env('oai.pageSize', 100)));

        $this->xml = new XMLWriter();
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement('OAI-PMH');
        $this->xml->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $this->xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->xml->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $this->xml->writeElement('responseDate', gmdate('Y-m-d\TH:i:s\Z'));

        $args = $this->_arguments();
        $verb = $args['verb'] ?? null;

        if (isset($args['__repeated'])) {
            $this->_request([]);
            $this->_error('badArgument', 'Argumen tidak boleh diulang: ' . $args['__repeated']);
        } elseif (! is_string($verb) || ! isset(self::VERBS[$verb])) {
            $this->_request([]);
            $this->_error('badVerb', $verb === null ? 'Argumen verb wajib diisi.' : 'Verb tidak dikenal.');
        } elseif (($problem = $this->_validateArguments($verb, $args)) !== null) {
            $this->_request([]);
            $this->_error('badArgument', $problem);
        } else {
            $this->_request($args);
            $this->{'_' . lcfirst($verb)}($args);
        }

        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->response
            ->setHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->setBody($this->xml->outputMemory());
    }

    // ------------------------------------------------------------------ verbs

    private function _identify(array $args): void
    {
        $this->xml->startElement('Identify');
        $this->xml->writeElement('repositoryName', $this->repo->repositoryName());
        $this->xml->writeElement('baseURL', $this->_baseURL());
        $this->xml->writeElement('protocolVersion', '2.0');
        $this->xml->writeElement('adminEmail', $this->repo->adminEmail());
        $this->xml->writeElement('earliestDatestamp', $this->repo->earliestDatestamp());
        $this->xml->writeElement('deletedRecord', 'no');
        $this->xml->writeElement('granularity', 'YYYY-MM-DDThh:mm:ssZ');

        $this->xml->startElement('description');
        $this->xml->startElement('oai-identifier');
        $this->xml->writeAttribute('xmlns', 'http://www.openarchives.org/OAI/2.0/oai-identifier');
        $this->xml->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd');
        $this->xml->writeElement('scheme', 'oai');
        $this->xml->writeElement('repositoryIdentifier', $this->repoId);
        $this->xml->writeElement('delimiter', ':');
        $this->xml->writeElement('sampleIdentifier', 'oai:' . $this->repoId . ':1');
        $this->xml->endElement();
        $this->xml->endElement();

        $this->xml->endElement();
    }

    private function _listMetadataFormats(array $args): void
    {
        if (isset($args['identifier']) && $this->_recordFromIdentifier($args['identifier']) === null) {
            $this->_error('idDoesNotExist', 'Identifier tidak ditemukan.');

            return;
        }

        $this->xml->startElement('ListMetadataFormats');
        foreach (self::FORMATS as $prefix => $format) {
            $this->xml->startElement('metadataFormat');
            $this->xml->writeElement('metadataPrefix', $prefix);
            $this->xml->writeElement('schema', $format['schema']);
            $this->xml->writeElement('metadataNamespace', $format['namespace']);
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    private function _listSets(array $args): void
    {
        if (isset($args['resumptionToken'])) {
            $this->_error('badResumptionToken', 'ListSets tidak memakai resumptionToken.');

            return;
        }

        $this->xml->startElement('ListSets');
        foreach ($this->repo->sets() as $set) {
            $this->xml->startElement('set');
            $this->xml->writeElement('setSpec', $set['spec']);
            $this->xml->writeElement('setName', $set['name']);
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    private function _listIdentifiers(array $args): void
    {
        $this->_list($args, false);
    }

    private function _listRecords(array $args): void
    {
        $this->_list($args, true);
    }

    private function _getRecord(array $args): void
    {
        if (! isset(self::FORMATS[$args['metadataPrefix']])) {
            $this->_error('cannotDisseminateFormat', 'metadataPrefix tidak didukung.');

            return;
        }
        $record = $this->_recordFromIdentifier($args['identifier']);
        if ($record === null) {
            $this->_error('idDoesNotExist', 'Identifier tidak ditemukan.');

            return;
        }

        $this->xml->startElement('GetRecord');
        $this->_record($this->repo->hydrate([$record])[0], true);
        $this->xml->endElement();
    }

    // ------------------------------------------------------------------ list + paging

    private function _list(array $args, bool $withMetadata): void
    {
        $verb = $withMetadata ? 'ListRecords' : 'ListIdentifiers';

        if (isset($args['resumptionToken'])) {
            $state = $this->_decodeToken($args['resumptionToken']);
            if ($state === null) {
                $this->_error('badResumptionToken', 'resumptionToken tidak valid atau kedaluwarsa.');

                return;
            }
        } else {
            $state = [
                'p' => $args['metadataPrefix'],
                'f' => isset($args['from']) ? $this->_normalizeDate($args['from'], false) : null,
                'u' => isset($args['until']) ? $this->_normalizeDate($args['until'], true) : null,
                's' => $args['set'] ?? null,
                'a' => 0,
                'c' => 0,
            ];
        }

        if (! isset(self::FORMATS[$state['p']])) {
            $this->_error('cannotDisseminateFormat', 'metadataPrefix tidak didukung.');

            return;
        }
        if ($state['s'] !== null && ! $this->repo->setExists($state['s'])) {
            $this->_error('noRecordsMatch', 'Set tidak ditemukan.');

            return;
        }

        $page = $this->repo->page($state['f'], $state['u'], $state['s'], (int) $state['a'], $this->pageSize);
        if ($page['rows'] === []) {
            $this->_error('noRecordsMatch', 'Tidak ada rekaman yang cocok.');

            return;
        }

        $rows = $withMetadata ? $this->repo->hydrate($page['rows']) : $page['rows'];

        $this->xml->startElement($verb);
        foreach ($rows as $row) {
            $withMetadata ? $this->_record($row, true) : $this->_header($row);
        }

        if ($page['hasMore'] || isset($args['resumptionToken'])) {
            $this->xml->startElement('resumptionToken');
            $this->xml->writeAttribute('completeListSize', (string) $page['total']);
            $this->xml->writeAttribute('cursor', (string) $state['c']);
            if ($page['hasMore']) {
                $last = end($page['rows']);
                $next = ['a' => (int) $last->biblio_id, 'c' => (int) $state['c'] + count($page['rows'])] + $state;
                $this->xml->text($this->_encodeToken($next));
            }
            $this->xml->endElement();
        }

        $this->xml->endElement();
    }

    private function _encodeToken(array $state): string
    {
        $json = json_encode($state);

        return rtrim(strtr(base64_encode($json . '|' . substr(hash_hmac('sha256', $json, $this->_tokenKey()), 0, 16)), '+/', '-_'), '=');
    }

    private function _decodeToken(string $token): ?array
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || ! str_contains($raw, '|')) {
            return null;
        }
        [$json, $sig] = explode('|', $raw, 2);
        if (! hash_equals(substr(hash_hmac('sha256', $json, $this->_tokenKey()), 0, 16), $sig)) {
            return null;
        }
        $state = json_decode($json, true);
        if (! is_array($state) || ! isset($state['p'], $state['a'], $state['c'])) {
            return null;
        }

        return $state + ['f' => null, 'u' => null, 's' => null];
    }

    private function _tokenKey(): string
    {
        return (string) (config('Encryption')->key ?: 'rangkui-oai') . $this->repoId;
    }

    // ------------------------------------------------------------------ record output

    private function _header(object $row): void
    {
        $this->xml->startElement('header');
        $this->xml->writeElement('identifier', $this->_identifier((int) $row->biblio_id));
        $this->xml->writeElement('datestamp', $this->repo->toUtc($row->last_update) ?? $this->repo->toUtc($row->input_date) ?? '1970-01-01T00:00:00Z');
        if (! empty($row->item_type_id) && ($type = $this->repo->itemTypeToken($row->item_type_id)) !== null) {
            $this->xml->writeElement('setSpec', 'type:' . $type);
        }
        if (! empty($row->code_ministry)) {
            $this->xml->writeElement('setSpec', 'prodi:' . $this->repo->setToken((string) $row->code_ministry));
        }
        $this->xml->endElement();
    }

    private function _record(object $row, bool $withMetadata): void
    {
        $this->xml->startElement('record');
        $this->_header($row);
        if ($withMetadata) {
            $this->xml->startElement('metadata');
            $this->_oaiDc($row);
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    private function _oaiDc(object $r): void
    {
        $x = $this->xml;
        $x->startElement('oai_dc:dc');
        $x->writeAttribute('xmlns:oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $x->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $x->writeAttribute('xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd');

        // Teks biasa ditulis apa adanya (XMLWriter yang meng-escape). Hanya abstrak yang
        // boleh berisi HTML sehingga tag-nya dibuang.
        $dc = static function (string $element, $value, bool $html = false) use ($x): void {
            $value = (string) $value;
            if ($html) {
                $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '');
            if ($value !== '') {
                $x->writeElement('dc:' . $element, $value);
            }
        };

        $dc('title', $r->title);
        foreach ($r->authors as $name) {
            $dc('creator', $name);
        }
        foreach ($r->topics as $topic) {
            $dc('subject', $topic);
        }
        if (! empty($r->classification)) {
            $dc('subject', $r->classification);
        }
        $dc('description', $r->notes, true);
        foreach ($r->supervisors as $name) {
            $dc('contributor', $name);
        }
        foreach ($r->examiners as $name) {
            $dc('contributor', $name);
        }
        foreach ($r->contributors as $name) {
            $dc('contributor', $name);
        }
        $dc('publisher', $r->publisherName ?? ($r->prodi->university ?? null));
        $dc('date', $r->publish_year);

        $code = strtoupper((string) ($r->itemType->item_type_code ?? ''));
        if (isset(OaiRepository::EU_REPO_TYPES[$code])) {
            $dc('type', OaiRepository::EU_REPO_TYPES[$code]);
        }
        $dc('type', $r->itemType->item_type_name ?? null);
        $dc('type', 'Text');

        $publicFiles = array_filter($r->files, static fn ($f) => $f->access_type === 'public' && empty($f->access_limit));
        if ($r->files !== []) {
            $dc('format', 'application/pdf');
        }

        $dc('identifier', base_url('beranda/detail/' . slim_encrypt($r->biblio_id)));
        foreach ($publicFiles as $f) {
            $dc('identifier', base_url('beranda/file/' . (int) $r->biblio_id . '/' . (int) $f->file_id));
        }
        if (! empty($r->isbn_issn)) {
            $dc('identifier', 'ISBN/ISSN: ' . $r->isbn_issn);
        }
        if (! empty($r->url_crossref)) {
            $dc('relation', $r->url_crossref);
        }

        $dc('language', $r->language_id);
        if (! empty($r->prodi)) {
            $dc('relation', trim($r->prodi->name_prodi . ($r->prodi->university ? ', ' . $r->prodi->university : '')));
        }

        $dc('rights', $publicFiles !== [] ? 'info:eu-repo/semantics/openAccess' : ($r->files !== [] ? 'info:eu-repo/semantics/restrictedAccess' : 'info:eu-repo/semantics/closedAccess'));
        $dc('rights', $r->licenseName);
        $dc('rights', $r->copyrightName);

        $x->endElement();
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Kumpulkan argumen dari GET atau POST, mendeteksi argumen berulang.
     */
    private function _arguments(): array
    {
        $raw = strtoupper($this->request->getMethod()) === 'POST'
            ? (string) $this->request->getBody()
            : (string) ($this->request->getServer('QUERY_STRING') ?? '');

        $args = [];
        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_map('urldecode', explode('=', $pair, 2) + [1 => '']);
            if (array_key_exists($k, $args)) {
                return ['__repeated' => $k];
            }
            $args[$k] = $v;
        }

        return $args;
    }

    private function _validateArguments(string $verb, array $args): ?string
    {
        $rule    = self::VERBS[$verb];
        $given   = array_diff(array_keys($args), ['verb']);
        $allowed = array_merge($rule['required'], $rule['optional'], $rule['exclusive'] ? [$rule['exclusive']] : []);

        if (($illegal = array_diff($given, $allowed)) !== []) {
            return 'Argumen tidak sah: ' . implode(', ', $illegal);
        }

        if ($rule['exclusive'] !== null && isset($args[$rule['exclusive']])) {
            return count($given) === 1 ? null : 'resumptionToken harus menjadi satu-satunya argumen.';
        }

        foreach ($rule['required'] as $req) {
            if (! isset($args[$req]) || $args[$req] === '') {
                return "Argumen {$req} wajib diisi.";
            }
        }

        $from  = $args['from'] ?? null;
        $until = $args['until'] ?? null;
        foreach (['from' => $from, 'until' => $until] as $name => $value) {
            if ($value !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}Z)?$/', $value)) {
                return "Format {$name} tidak valid (YYYY-MM-DD atau YYYY-MM-DDThh:mm:ssZ).";
            }
        }
        if ($from !== null && $until !== null && strlen($from) !== strlen($until)) {
            return 'from dan until harus memakai granularitas yang sama.';
        }
        if ($from !== null && $until !== null && $this->_normalizeDate($from, false) > $this->_normalizeDate($until, true)) {
            return 'from tidak boleh lebih besar dari until.';
        }

        return null;
    }

    private function _normalizeDate(string $value, bool $endOfDay): string
    {
        return strlen($value) === 10 ? $value . ($endOfDay ? 'T23:59:59Z' : 'T00:00:00Z') : $value;
    }

    private function _request(array $args): void
    {
        $this->xml->startElement('request');
        foreach ($args as $k => $v) {
            if ($k !== '__repeated') {
                $this->xml->writeAttribute($k, $v);
            }
        }
        $this->xml->text($this->_baseURL());
        $this->xml->endElement();
    }

    private function _error(string $code, string $message): void
    {
        $this->xml->startElement('error');
        $this->xml->writeAttribute('code', $code);
        $this->xml->text($message);
        $this->xml->endElement();
    }

    private function _identifier(int $id): string
    {
        return 'oai:' . $this->repoId . ':' . $id;
    }

    private function _recordFromIdentifier(string $identifier): ?object
    {
        $prefix = 'oai:' . $this->repoId . ':';
        if (! str_starts_with($identifier, $prefix) || ! ctype_digit(substr($identifier, strlen($prefix)))) {
            return null;
        }

        return $this->repo->find((int) substr($identifier, strlen($prefix)));
    }

    private function _repositoryIdentifier(): string
    {
        $configured = trim((string) env('oai.repositoryIdentifier', ''));
        $host       = $configured !== '' ? $configured : (string) parse_url(base_url(), PHP_URL_HOST);

        return preg_replace('/[^A-Za-z0-9.\-]/', '', $host) ?: 'rangkui.local';
    }

    private function _baseURL(): string
    {
        return rtrim(base_url('oai'), '/');
    }
}

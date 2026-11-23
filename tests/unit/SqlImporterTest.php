<?php

use App\Libraries\SqlImporter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SqlImporterTest extends CIUnitTestCase
{
    public function testSemicolonInsideStringsDoesNotSplitStatement(): void
    {
        $sql = "INSERT INTO a VALUES ('x;y', \"q\\\"; z\", 'it''s;'); SELECT 1;";

        $this->assertSame(
            ["INSERT INTO a VALUES ('x;y', \"q\\\"; z\", 'it''s;')", 'SELECT 1'],
            SqlImporter::split($sql)
        );
    }

    public function testCommentsAreDroppedButConditionalCommentsKept(): void
    {
        $sql = "-- komentar; baris\nSELECT 1; # lain;\n/* blok; */ SELECT 2;\n/*!40101 SET NAMES utf8 */;";

        $this->assertSame(['SELECT 1', 'SELECT 2', '/*!40101 SET NAMES utf8 */'], SqlImporter::split($sql));
    }

    public function testBacktickIdentifiersAreRespected(): void
    {
        $this->assertSame(['SELECT `a;b` FROM t'], SqlImporter::split('SELECT `a;b` FROM t'));
    }

    public function testBundledSchemaSplitsIntoStatements(): void
    {
        $file = ROOTPATH . 'admin/brantas.sql';
        if (! is_file($file)) {
            $this->markTestSkipped('admin/brantas.sql sudah dihapus (aplikasi terpasang).');
        }

        $statements = SqlImporter::split(file_get_contents($file));

        $this->assertGreaterThan(100, count($statements));
        $this->assertNotEmpty(preg_grep('/^CREATE TABLE `biblio`/', $statements));
    }
}

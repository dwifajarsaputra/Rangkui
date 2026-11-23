<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HelpersTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('main');
    }

    public function testEnvSetReplacesActiveLineAndQuotes(): void
    {
        $env = "# CI_ENVIRONMENT = production\nCI_ENVIRONMENT = development\nx=1\n";

        $this->assertSame(
            "# CI_ENVIRONMENT = production\nCI_ENVIRONMENT = \"production\"\nx=1\n",
            env_set($env, 'CI_ENVIRONMENT', 'production')
        );
    }

    public function testEnvSetCannotInjectNewLines(): void
    {
        $out = env_set("a=1\n", 'recaptcha.publicKey', "abc\nCI_ENVIRONMENT=development");

        $this->assertStringNotContainsString("\nCI_ENVIRONMENT", $out);
        $this->assertStringContainsString('recaptcha.publicKey = "abcCI_ENVIRONMENT=development"', $out);
    }

    public function testEnvSetEscapesQuotesAndBackslashes(): void
    {
        $this->assertStringContainsString('k = "p\\"a\\\\s$1"', env_set('', 'k', 'p"a\\s$1'));
    }

    public function testEnvSetUncommentsExistingKey(): void
    {
        $this->assertSame("encryption.key = \"abc\"\n", env_set("# encryption.key =\n", 'encryption.key', 'abc'));
    }

    public function testDateHelpersTolerateEmptyValues(): void
    {
        $this->assertSame('-', datetimeIdn(null));
        $this->assertSame('-', datetimeIdn(''));
        $this->assertSame('-', TanggalIndo('0000-00-00'));
        $this->assertSame('24 Nov 2026', datetimeIdn('2026-11-24 04:00:00'));
        $this->assertSame('24 November 2026', TanggalIndo('2026-11-24'));
    }

    public function testDelImageRejectsPathTraversal(): void
    {
        $this->assertSame('-1.', delImage('images/docs', '..'));
        $this->assertSame('-1.', delImage('repository', ''));
    }
}

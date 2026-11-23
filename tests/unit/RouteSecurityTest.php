<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FilterTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regresi keamanan: semua halaman admin wajib login & semua request pengubah data wajib CSRF.
 * Bila menambah modul admin baru, ia otomatis terlindungi — tes ini memastikan tetap begitu.
 *
 * @internal
 */
final class RouteSecurityTest extends CIUnitTestCase
{
    use FilterTestTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminRoutes(): iterable
    {
        foreach ([
            'home', 'home/stats', 'bibliography', 'bibliography/add', 'bibliography/save',
            'membership', 'membership/save', 'membership/update', 'export/download-counter',
            'profile', 'profile/save', 'master/gmd', 'master/pengarang/save',
            'report/recap', 'sistem/pustakawan', 'sistem/backups', 'sistem/logs/delete',
            'sistem/pengaturan-sistem/update-key', 'some/future/module',
        ] as $route) {
            yield $route => [$route];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicRoutes(): iterable
    {
        foreach (['/', 'beranda', 'beranda/search', 'beranda/detail/abc', 'beranda/file/1/2', 'oai', 'login', 'login/auth', 'logout', 'information'] as $route) {
            yield $route => [$route];
        }
    }

    #[DataProvider('adminRoutes')]
    public function testAdminRoutesRequireLogin(string $route): void
    {
        $this->assertFilter($route, 'before', 'auth');
    }

    #[DataProvider('publicRoutes')]
    public function testPublicRoutesDoNotRequireLogin(string $route): void
    {
        $this->assertNotFilter($route, 'before', 'auth');
    }

    #[DataProvider('adminRoutes')]
    public function testAdminRoutesAreCsrfProtected(string $route): void
    {
        $this->assertFilter($route, 'before', 'csrf');
    }

    public function testLoginIsCsrfProtected(): void
    {
        $this->assertFilter('login/auth', 'before', 'csrf');
    }

    public function testOaiIsNotCsrfProtected(): void
    {
        $this->assertNotFilter('oai', 'before', 'csrf');
    }
}

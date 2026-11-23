<?php

use App\Controllers\InstallController;
use App\Modules\Login\Controllers\Login;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Satu berkas rute untuk dua keadaan:
 *  - BELUM terpasang (folder admin/ masih ada & belum ada writable/installed.lock): hanya wizard instalasi.
 *  - SUDAH terpasang: rute aplikasi + rute dari setiap app/Modules/<Modul>/Routes.php.
 *
 * Versi sebelumnya menimpa berkas ini saat instalasi sehingga `git pull` berikutnya bentrok.
 */
$isInstalled = is_file(WRITEPATH . 'installed.lock') || ! is_file(ROOTPATH . 'admin/conf.inc.php');

if (! $isInstalled) {
    $routes->get('/', [InstallController::class, 'install']);
    $routes->match(['GET', 'POST'], 'install/fresh', [InstallController::class, 'fresh']);
    $routes->match(['GET', 'POST'], 'install/upgrade', [InstallController::class, 'upgrade']);
    $routes->get('install/type-installation', [InstallController::class, 'type']);
    $routes->post('install/process', [InstallController::class, 'process']);

    return;
}

$routes->setTranslateURIDashes(true);

$routes->get('/', '\App\Modules\Beranda\Controllers\BerandaController::index');
$routes->get('oai', 'OaiPmhController::index');
$routes->post('oai', 'OaiPmhController::index');
$routes->get('logout', [Login::class, 'logout']);

$modulesPath = APPPATH . 'Modules/';

foreach (scandir($modulesPath) ?: [] as $moduleDir) {
    if ($moduleDir === '.' || $moduleDir === '..' || ! is_dir($modulesPath . $moduleDir)) {
        continue;
    }

    $routesFile = $modulesPath . $moduleDir . '/Routes.php';
    if (is_file($routesFile)) {
        include $routesFile;
    }
}

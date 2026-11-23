<?php

use App\Modules\Info\Controllers\InfoController;

$routes->get('information', [InfoController::class, 'index']);

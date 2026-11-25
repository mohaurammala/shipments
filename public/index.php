<?php

declare(strict_types=1);

/**
 * Front controller: .htaccess sends every request that is not a real file here.
 */

use App\Http\Kernel;
use App\Http\Request;

/** @var Kernel $kernel */
$kernel = require dirname(__DIR__).'/src/bootstrap.php';

$kernel->handle(Request::fromGlobals())->send();

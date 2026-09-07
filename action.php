<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/Counter.php'; // exemple hors du scope autoload PSR-4 de src/

use HyperPHP\Core\Kernel;
use HyperPHP\Core\StateSigner;
use HyperPHP\Examples\Counter\Counter;
use HyperPHP\Http\Request;
use HyperPHP\Http\Response;

$signer = new StateSigner(secretKey: getenv('HYPERPHP_KEY') ?: 'dev-only-key-do-not-use-in-prod');

$kernel = new Kernel($signer);
$kernel->register('counter', Counter::class);

$result = $kernel->handle(Request::fromGlobals());

if (isset($result['error'])) {
    Response::error($result['error'], 422);
    exit;
}

Response::json($result);

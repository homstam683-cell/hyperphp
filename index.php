<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/Counter.php'; // exemple hors du scope autoload PSR-4 de src/

use HyperPHP\Core\Kernel;
use HyperPHP\Core\StateSigner;
use HyperPHP\Examples\Counter\Counter;

// En production, HYPERPHP_KEY doit venir d'une variable d'environnement,
// jamais être codée en dur. Pour la démo locale uniquement :
$signer = new StateSigner(secretKey: getenv('HYPERPHP_KEY') ?: 'dev-only-key-do-not-use-in-prod');

$kernel = new Kernel($signer);
$kernel->register('counter', Counter::class);

$html = $kernel->mount('counter');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>HyperPHP — Démo Counter</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 480px; margin: 4rem auto; text-align: center; }
        button { font-size: 1rem; padding: 0.5rem 1rem; margin: 0 0.25rem; cursor: pointer; }
    </style>
</head>
<body>
    <h1>HyperPHP v0.1</h1>
    <p><em>Écrit uniquement en PHP. Le patch DOM et la signature HMAC tournent en arrière-plan.</em></p>

    <?= $html ?>

    <script src="/runtime/hyperphp.js" data-endpoint="/action.php"></script>
</body>
</html>

<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$root = dirname(__DIR__, 2);
return (new Config())
    ->setRiskyAllowed(false)
    ->setRules(['@PSR12' => true])
    ->setFinder((new Finder())->in([
        $root.'/app/Mcp',
        $root.'/tests/Feature/Mcp',
        $root.'/tests/Unit/Mcp',
        $root.'/tests/Fixtures/Mcp',
        $root.'/tools/mcp',
    ])->append([$root.'/config/mcp.php']));

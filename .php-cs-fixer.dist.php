<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->exclude('Fixtures')
    ->append([
        __DIR__ . '/public/index.php',
        __DIR__ . '/xpsr',
    ]);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS3x0' => true])
    ->setFinder($finder);

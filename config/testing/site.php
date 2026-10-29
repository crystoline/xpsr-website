<?php

declare(strict_types=1);

// Tests read the documentation from fixtures and never touch the network.
$site = require dirname(__DIR__) . '/site.php';

return [
    'docs' => [
        'repository' => 'file:///nonexistent',
        'sources' => dirname(__DIR__, 2) . '/tests/Fixtures/sources',
        'next_path' => null,
    ] + $site['docs'],
    'build' => sys_get_temp_dir() . '/xpsr-website-tests/build',
];

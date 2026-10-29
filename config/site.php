<?php

declare(strict_types=1);

return [
    'name' => 'XPSR',
    'description' => 'XPSR is a PHP framework built on PSR standards. Pick the router, the view engine, and the ORM; keep your application code.',
    'github' => 'https://github.com/crystoline/xpsr',
    'website_github' => 'https://github.com/crystoline/xpsr-website',
    'packagist' => 'https://packagist.org/packages/crystoline/xpsr',

    'docs' => [
        // The framework repository the documentation comes from.
        'repository' => env('DOCS_REPOSITORY', 'https://github.com/crystoline/xpsr.git'),
        // Where site:fetch keeps its mirror of that repository and one directory per version.
        'sources' => dirname(__DIR__) . '/var/sources',
        // A local checkout of the framework to read the development docs from instead of
        // master, to preview documentation changes before they are pushed.
        'next_path' => env('DOCS_NEXT_PATH') ?: null,
        'next_branch' => 'master',
        // Each minor release from this one on gets documentation, from its latest patch tag.
        'since' => '0.3',
        // Tags that are not XPSR releases. v1.0.0 is the 2022 prototype.
        'ignore_tags' => ['v1.0.0'],
        // The sidebar for versions whose docs/ has no README.md (0.3 and earlier).
        'fallback_navigation' => dirname(__DIR__) . '/resources/navigation.md',
    ],

    // site:build writes the static site here.
    'build' => dirname(__DIR__) . '/build',
];

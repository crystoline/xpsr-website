<?php

declare(strict_types=1);

use App\Http\Middleware\NotFoundPage;
use App\Registry\SiteServiceRegistry;
use Crystoline\Xpsr\Registry\ConsoleServiceRegistry;
use Crystoline\Xpsr\Registry\FactoryRegistry;
use Crystoline\Xpsr\Registry\RouteServiceRegistry;
use Crystoline\Xpsr\Registry\ViewServiceRegistry;

return [
    'name' => env('APP_NAME', 'XPSR'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    // The public address. Canonical links, the sitemap, and the exported CNAME use it.
    'url' => env('APP_URL', 'https://xpsr.crysto.name.ng'),
    'timezone' => 'UTC',
    'namespace' => 'App',
    'source_dir' => 'app',
    'view_dir' => dirname(__DIR__) . '/views',
    'view_cache_dir' => false,
    'registry' => [
        FactoryRegistry::class,
        RouteServiceRegistry::class,
        ViewServiceRegistry::class,
        ConsoleServiceRegistry::class,
        SiteServiceRegistry::class,
    ],
    'middleware' => [
        // Renders the site's 404 page, and adds the trailing slash that page URLs end with.
        NotFoundPage::class,
    ],
];

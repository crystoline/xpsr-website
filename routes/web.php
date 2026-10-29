<?php

declare(strict_types=1);

use App\Http\Controller\DocsController;
use App\Http\Controller\HomeController;
use App\Http\Controller\MetaController;
use Crystoline\Xpsr\Routing\RouteCollection;

// Every page URL ends with a slash: the static export writes it as a directory's index.html.
return static function (RouteCollection $routes): void {
    $routes->get('/', [HomeController::class, 'index'])->name('home');

    $routes->get('/docs/', [DocsController::class, 'latest'])->name('docs');
    $routes->get('/docs/{version}/', [DocsController::class, 'index'])->name('docs.index');
    $routes->get('/docs/{version}/{page}/', [DocsController::class, 'page'])->name('docs.page');
    $routes->get('/docs/{version}/packages/{package}/', [DocsController::class, 'package'])->name('docs.package');

    $routes->get('/sitemap.xml', [MetaController::class, 'sitemap'])->name('sitemap');
    $routes->get('/robots.txt', [MetaController::class, 'robots'])->name('robots');
};

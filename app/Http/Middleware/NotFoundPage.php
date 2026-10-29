<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Site\SiteContext;
use Crystoline\Xpsr\Http\Exception\NotFoundHttpException;
use Crystoline\Xpsr\Http\Response\ResponseBuilder;
use Crystoline\Xpsr\View\ViewHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers unknown addresses with the site's own 404 page.
 *
 * Page URLs end with a slash, as static hosts serve them (docs/0.3/routing/index.html). A
 * request without the slash for a page that exists is redirected to it, as those hosts do.
 */
final class NotFoundPage implements MiddlewareInterface
{
    public function __construct(
        private readonly SiteContext $site,
        private readonly ViewHandlerInterface $views,
        private readonly ResponseBuilder $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (NotFoundHttpException $exception) {
            $uri = $request->getUri();
            $path = $uri->getPath();
            if (!str_ends_with($path, '/') && !str_contains(basename($path), '.')) {
                try {
                    $handler->handle($request->withUri($uri->withPath($path . '/')));
                    $location = $path . '/' . ($uri->getQuery() === '' ? '' : '?' . $uri->getQuery());

                    return $this->responses->html('', 301)->withHeader('Location', $location);
                } catch (NotFoundHttpException) {
                    // Not a page with the slash either.
                }
            }

            $html = $this->views->render('errors.not-found', $this->site->with(['title' => 'Page not found', 'path' => $path]));

            return $this->responses->html($html, 404);
        }
    }
}

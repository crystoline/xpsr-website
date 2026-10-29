<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Docs\DocsLibrary;
use App\Site\SiteContext;
use Crystoline\Xpsr\Http\Response\ResponseBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Files for search engines. */
final class MetaController
{
    public function __construct(
        private readonly DocsLibrary $docs,
        private readonly SiteContext $site,
        private readonly ResponseBuilder $responses,
    ) {}

    /** The home page and the latest release's documentation. */
    public function sitemap(ServerRequestInterface $request): ResponseInterface
    {
        $base = $this->site->baseUrl();
        $urls = [$base . '/'];
        $latest = $this->docs->versions()->latest();
        if ($latest !== null) {
            foreach ($this->docs->pages($latest) as $page) {
                $urls[] = $base . $this->docs->url($latest, $page['slug']);
            }
        }
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>' . htmlspecialchars($url, ENT_XML1) . "</loc></url>\n";
        }
        $xml .= "</urlset>\n";

        return $this->responses->html($xml)->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(ServerRequestInterface $request): ResponseInterface
    {
        $text = "User-agent: *\nAllow: /\n\nSitemap: {$this->site->baseUrl()}/sitemap.xml\n";

        return $this->responses->html($text)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}

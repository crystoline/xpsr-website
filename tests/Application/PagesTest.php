<?php

declare(strict_types=1);

namespace Tests\Application;

use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

/**
 * The pages, requested through the HTTP kernel with the fixture documentation:
 * release 0.3 (no docs/README.md, so the fallback sidebar) and next.
 */
final class PagesTest extends TestCase
{
    private function page(string $path, int $status = 200): string
    {
        $response = $this->get($path);
        self::assertSame($status, $response->getStatusCode(), "GET {$path}");

        return (string) $response->getBody();
    }

    public function testTheHomePageListsThePackagesAndLinksToTheTutorial(): void
    {
        $html = $this->page('/');

        self::assertStringContainsString('<a class="button primary" href="/docs/0.3/getting-started/">', $html);
        self::assertStringContainsString('Latest release 0.3', $html);
        self::assertMatchesRegularExpression('~href="/docs/0.3/packages/cache/">\s*<strong>xpsr-cache</strong>~', $html);
        self::assertMatchesRegularExpression('~href="/docs/next/packages/mail/">\s*<strong>xpsr-mail</strong>\s*<span class="pill">Next release</span>~', $html);
        self::assertMatchesRegularExpression('~<div class="package">\s*<strong>xpsr-framework</strong>~', $html, 'A package without a README page is not a link.');
        self::assertStringContainsString('<link rel="canonical" href="https://xpsr.test/">', $html);
    }

    public function testDocsLeadsToTheLatestRelease(): void
    {
        $html = $this->page('/docs/');

        self::assertStringContainsString('<meta http-equiv="refresh" content="0; url=/docs/0.3/">', $html);
    }

    public function testAReleaseWithoutANavigationFileUsesTheFallbackSidebar(): void
    {
        $html = $this->page('/docs/0.3/');

        self::assertStringContainsString('XPSR 0.3 documentation', $html);
        self::assertStringContainsString('<a href="/docs/0.3/routing/">Routing</a>', $html);
        self::assertStringContainsString('<a href="/docs/0.3/changelog/">Changelog</a>', $html);
        self::assertStringNotContainsString('/docs/0.3/views/', $html, '0.3 has no views guide.');
        self::assertStringContainsString('Browse these docs on GitHub', $html);
    }

    public function testADevelopmentPage(): void
    {
        $html = $this->page('/docs/next/routing/');

        self::assertStringContainsString('<title>Portable routing · XPSR</title>', $html);
        self::assertStringContainsString('These are the docs of the development version', $html);
        self::assertStringContainsString('<a href="/docs/next/routing/" aria-current="page">Routing</a>', $html);
        self::assertStringContainsString('<a href="/docs/next/views/">Views</a>', $html);
        self::assertStringNotContainsString('Missing', $html, 'Pages whose file does not exist are left out.');
        self::assertStringContainsString('href="https://github.com/crystoline/xpsr/edit/master/docs/ROUTING.md"', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://xpsr.test/docs/0.3/routing/">', $html);
        self::assertStringContainsString('data-pagefind-filter="version:next"', $html);
        self::assertStringContainsString('<a href="#appview_engine-and-twigs--">', $html);
        self::assertMatchesRegularExpression('~rel="prev"><small>.*?Previous</small>Getting started</a>~s', $html);
        self::assertMatchesRegularExpression('~rel="next"><small>Next .*?</small>Views</a>~s', $html);
    }

    public function testAReleasePage(): void
    {
        $html = $this->page('/docs/0.3/routing/');

        self::assertStringNotContainsString('class="banner', $html);
        self::assertStringContainsString('href="https://github.com/crystoline/xpsr/blob/v0.3.0/docs/ROUTING.md"', $html);
        self::assertStringContainsString('data-pagefind-filter="version:0.3"', $html);
        self::assertStringContainsString('<a href="/docs/next/routing/">next (master)</a>', $html);
    }

    public function testTheVersionMenuFallsBackToTheIndexOfAVersionWithoutThePage(): void
    {
        $html = $this->page('/docs/next/views/');

        self::assertStringContainsString('<a href="/docs/0.3/">0.3<span class="tag">latest</span></a>', $html);
    }

    public function testPackagePagesUseTheirSidebarTitle(): void
    {
        $html = $this->page('/docs/next/packages/cache/');

        self::assertStringContainsString('<title>Cache · XPSR</title>', $html);
        self::assertStringContainsString('<p class="breadcrumb" data-pagefind-ignore>Packages</p>', $html);
    }

    public function testUnknownAddressesGetTheNotFoundPage(): void
    {
        foreach (['/docs/9.9/', '/docs/0.3/views/', '/docs/next/nothing/', '/nothing.html'] as $path) {
            self::assertStringContainsString('This page does not exist', $this->page($path, 404));
        }
    }

    public function testAPageWithoutItsTrailingSlashRedirects(): void
    {
        $response = $this->get('/docs/0.3/routing?x=1');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/docs/0.3/routing/?x=1', $response->getHeaderLine('Location'));
        self::assertSame(404, $this->get('/docs/0.3/nothing')->getStatusCode());
    }

    public function testTheSitemapListsTheLatestRelease(): void
    {
        $response = $this->get('/sitemap.xml');
        $xml = (string) $response->getBody();

        self::assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<loc>https://xpsr.test/</loc>', $xml);
        self::assertStringContainsString('<loc>https://xpsr.test/docs/0.3/routing/</loc>', $xml);
        self::assertStringNotContainsString('/docs/next/', $xml);
        self::assertStringContainsString('Sitemap: https://xpsr.test/sitemap.xml', (string) $this->get('/robots.txt')->getBody());
    }

    public function testAssetUrlsChangeWithTheirContents(): void
    {
        $hash = substr((string) md5_file(dirname(__DIR__, 2) . '/public/assets/site.css'), 0, 10);

        self::assertStringContainsString('href="/assets/site.css?v=' . $hash . '"', $this->page('/'));
    }

    protected function get(string $uri, array $headers = []): ResponseInterface
    {
        return parent::get($uri, $headers + ['Accept' => 'text/html']);
    }
}

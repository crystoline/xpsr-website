<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Docs\DocVersion;
use App\Markdown\Highlighter;
use App\Markdown\LinkResolver;
use App\Markdown\MarkdownRenderer;
use App\Markdown\RenderedMarkdown;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    private const GITHUB = 'https://github.com/crystoline/xpsr';

    private function render(string $path = 'docs/ROUTING.md'): RenderedMarkdown
    {
        $root = dirname(__DIR__) . '/Fixtures/sources/next';
        $version = new DocVersion('next', 'master', 'abc', $root);
        $pages = [
            'docs/README.md' => '/docs/next/',
            'docs/GETTING_STARTED.md' => '/docs/next/getting-started/',
            'docs/ROUTING.md' => '/docs/next/routing/',
            'docs/VIEWS.md' => '/docs/next/views/',
            'packages/cache/README.md' => '/docs/next/packages/cache/',
        ];

        return (new MarkdownRenderer(new LinkResolver(self::GITHUB), new Highlighter()))
            ->render((string) $version->read($path), $version, $path, $pages);
    }

    public function testLinksToPagesBecomeSiteLinksAndOtherFilesLinkToGithub(): void
    {
        $html = $this->render()->html;

        self::assertStringContainsString('href="/docs/next/getting-started/#create-a-project"', $html);
        self::assertStringContainsString('href="/docs/next/views/"', $html);
        self::assertStringContainsString('href="/docs/next/packages/cache/"', $html);
        self::assertStringContainsString('href="' . self::GITHUB . '/blob/master/packages/cache/src/CacheServiceRegistry.php#L10"', $html);
        self::assertStringContainsString('href="' . self::GITHUB . '/blob/master/docs/FRAMEWORK_PLAN.md"', $html);
        self::assertStringContainsString('href="' . self::GITHUB . '/tree/master/packages"', $html);
        self::assertStringContainsString('href="https://www.php-fig.org/psr/"', $html);
        self::assertStringContainsString('href="#declare-routes"', $html);
    }

    public function testLinksFromPackageReadmesResolveFromTheirDirectory(): void
    {
        self::assertStringContainsString('href="/docs/next/routing/#declare-routes"', $this->render('packages/cache/README.md')->html);
    }

    public function testLinksToMissingFilesAreReported(): void
    {
        self::assertSame(['links to NOWHERE.md, a file that does not exist'], $this->render()->problems);
    }

    public function testHeadingsGetTheIdsGithubGivesThem(): void
    {
        $result = $this->render();

        self::assertSame('Portable routing', $result->title);
        self::assertSame([
            ['level' => 2, 'text' => 'Declare routes', 'id' => 'declare-routes'],
            ['level' => 2, 'text' => "app.view_engine and Twig's {{ }}", 'id' => 'appview_engine-and-twigs--'],
            ['level' => 2, 'text' => 'Declare routes', 'id' => 'declare-routes-1'],
        ], $result->headings);
        self::assertStringContainsString('<h1>Portable routing</h1>', $result->html);
        self::assertStringContainsString('<h2 id="declare-routes">', $result->html);
        self::assertStringContainsString('class="heading-anchor"', $result->html);
    }

    public function testCodeIsHighlightedAndTablesScroll(): void
    {
        $html = $this->render()->html;

        self::assertStringContainsString('<div class="code-block" data-language="PHP"><pre class="phiki language-php', $html);
        self::assertStringContainsString('--phiki-dark-color', $html);
        self::assertStringContainsString('<div class="table-wrap"><table>', $html);
    }

    public function testCodeInUnknownLanguagesIsEscapedPlainText(): void
    {
        $block = (new Highlighter())->block("0 3 * * * <run>\n", 'cron');

        self::assertSame('<div class="code-block" data-language="cron"><pre class="phiki plain"><code>0 3 * * * &lt;run&gt;' . "\n" . '</code></pre></div>', $block);
    }
}

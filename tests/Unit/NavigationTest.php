<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Docs\Navigation;
use App\Docs\Paths;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    public function testSectionsAndPagesComeFromHeadingsAndListItems(): void
    {
        $navigation = Navigation::parse((string) file_get_contents(dirname(__DIR__) . '/Fixtures/sources/next/docs/README.md'));

        self::assertSame(['Start here', 'The basics', 'Packages', 'Project'], array_column($navigation->sections, 'title'));
        self::assertSame([
            ['title' => 'Routing', 'path' => 'docs/ROUTING.md'],
            ['title' => 'Views', 'path' => 'docs/VIEWS.md'],
            ['title' => 'Missing', 'path' => 'docs/MISSING.md'],
        ], $navigation->sections[1]['items']);
        self::assertSame('packages/cache/README.md', $navigation->sections[2]['items'][0]['path']);
        self::assertSame('UPGRADE.md', $navigation->sections[3]['items'][0]['path']);
    }

    public function testCommentsCodeBlocksAndOtherLinksAreNotPages(): void
    {
        $paths = array_column(Navigation::parse((string) file_get_contents(dirname(__DIR__) . '/Fixtures/sources/next/docs/README.md'))->pages(), 'path');

        self::assertNotContains('docs/HIDDEN.md', $paths);
        self::assertNotContains('docs/CODE.md', $paths);
        self::assertSame('docs/GETTING_STARTED.md', $paths[0], 'The link in the introduction is not a page; the list item is.');
        self::assertCount(8, $paths);
    }

    public function testFilteringDropsPagesAndEmptySections(): void
    {
        $navigation = Navigation::parse("## One\n\n- [A](A.md)\n\n## Two\n\n- [B](B.md)\n")
            ->filter(static fn(string $path): bool => $path === 'docs/A.md');

        self::assertSame([['title' => 'One', 'items' => [['title' => 'A', 'path' => 'docs/A.md']]]], $navigation->sections);
    }

    public function testTheNavigationCanBeWrittenBackAsMarkdown(): void
    {
        $navigation = Navigation::parse("## Packages\n\n- [Cache](../packages/cache/README.md)\n- [Routing](ROUTING.md)\n");

        self::assertSame(
            "# Docs\n\nHello.\n\n## Packages\n\n- [Cache](../packages/cache/README.md)\n- [Routing](ROUTING.md)\n\n",
            $navigation->toMarkdown('Docs', 'Hello.'),
        );
    }

    public function testPagePaths(): void
    {
        self::assertSame('docs/ROUTING.md', Paths::resolve('docs', 'ROUTING.md'));
        self::assertSame('packages/cache/README.md', Paths::resolve('docs', '../packages/./cache/README.md'));
        self::assertSame('docs/VIEWS.md', Paths::resolve('packages/cache', '/docs/VIEWS.md'));
        self::assertNull(Paths::resolve('docs', '../../outside.md'));

        self::assertSame('', Paths::slug('docs/README.md'));
        self::assertSame('getting-started', Paths::slug('docs/GETTING_STARTED.md'));
        self::assertSame('packages/view-blade', Paths::slug('packages/view-blade/README.md'));
        self::assertSame('upgrade', Paths::slug('UPGRADE.md'));
        self::assertNull(Paths::slug('docs/guides/deep.md'));
        self::assertNull(Paths::slug('packages/cache/src/Cache.php'));
    }
}
